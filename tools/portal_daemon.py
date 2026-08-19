#!/usr/bin/env python3
"""
 portal_daemon.py - hold ONE authenticated ssh session to portal.caltech.edu.

     python tools/portal_daemon.py            start it (asks for your password,
                                              then Duo; leave the window open)
     python tools/portal_daemon.py --status   is it up, and for how long
     python tools/portal_daemon.py --stop     shut it down
     python tools/portal_daemon.py --run CMD  run CMD on the server

 WHY THIS EXISTS
 ---------------
 Every ssh to portal costs a password and a Duo push. Setting the site up on
 the server is a dozen small commands, and a dozen Duo pushes is not a
 procedure anybody will follow twice. This authenticates once and then serves
 commands over a socket on 127.0.0.1, so the rest of the work costs nothing.

 ssh's own ControlMaster would be the obvious way to do this, and it does not
 work here: Git Bash's Unix-socket emulation cannot pass file descriptors
 between processes, so the master accepts `-O check` and then refuses to carry
 a real session (docs/DEPLOY-LOG.md, 2026-08-18 - three failed password
 attempts were logged against the account before that was understood).

 The pattern, and the 4-hour idle limit, are lifted from
 ~/Documents/1Research/HEA/vasp/hpc_monitor/ssh_daemon.py, which has held
 Duo-authenticated sessions to Caltech HPC for months. This is a separate,
 much smaller copy on purpose: that one is wired into a VASP job-submission
 guard and a cost ledger, and the club website has no business importing any
 of it.

 SECURITY, PLAINLY
 -----------------
 While this runs, anything on your computer that can reach 127.0.0.1 and read
 the token file can run commands on portal as you. That is a real grant, so:
 it listens on the loopback interface only, it requires a random token kept in
 a git-ignored state file, it shuts itself down after 4 hours idle, and it dies
 with the window. **Your password is never stored, never logged, and never
 written to disk** - it goes from the prompt straight into the ssh handshake.
 Stop it when you are done: `--stop`.
"""

import argparse
import getpass
import json
import os
import secrets
import socket
import sys
import threading
import time
from pathlib import Path

try:
    import paramiko
except ImportError:                                          # pragma: no cover
    paramiko = None

HERE       = Path(__file__).resolve().parent
STATE_FILE = HERE / ".portal-daemon.json"

HOST = "portal.caltech.edu"
KNOWN_HOST_FILE = HERE / ".portal-known-host"
PORT = 22
LISTEN_HOST = "127.0.0.1"
LISTEN_PORT = 19923          # 19922 is the VASP daemon's; do not collide with it
IDLE_LIMIT  = 4 * 3600       # shut down after four hours with nothing to do
MAX_TIMEOUT = 900            # no single remote command may hold the lock longer


# ---------------------------------------------------------------- state file --

def read_state():
    if not STATE_FILE.exists():
        return None
    try:
        return json.loads(STATE_FILE.read_text())
    except Exception:
        return None


def write_state(state):
    STATE_FILE.write_text(json.dumps(state, indent=2))
    try:
        os.chmod(STATE_FILE, 0o600)
    except OSError:
        pass                 # Windows filesystems shrug at this; the token is
                             # still only useful to something already on the box


def clear_state():
    try:
        STATE_FILE.unlink()
    except FileNotFoundError:
        pass


def username():
    """The same remembered username tools/deploy.sh uses, so there is one answer."""
    env = os.environ.get("ALPINE_DEPLOY_USER", "").strip()
    if env:
        return env
    saved = HERE.parent / ".deploy-user"
    if saved.exists():
        name = saved.read_text().strip()
        if name:
            return name
    return input("Your Caltech username: ").strip()


# --------------------------------------------------------------------- client --

def rpc(payload, timeout=MAX_TIMEOUT + 30):
    """Send one request to a running daemon. Returns the decoded reply."""
    state = read_state()
    if not state:
        raise SystemExit(
            "No daemon is running (no tools/.portal-daemon.json).\n"
            "Start one:  python tools/portal_daemon.py")
    payload = dict(payload, token=state["token"])
    s = socket.create_connection((LISTEN_HOST, state["port"]), timeout=15)
    s.settimeout(timeout)
    try:
        s.sendall((json.dumps(payload) + "\n").encode())
        buf = b""
        while not buf.endswith(b"\n"):
            chunk = s.recv(65536)
            if not chunk:
                break
            buf += chunk
    finally:
        s.close()
    if not buf.strip():
        raise SystemExit("The daemon accepted the connection and said nothing. "
                         "Check the window it is running in.")
    return json.loads(buf.decode())


def cmd_run(command, timeout):
    reply = rpc({"op": "run", "cmd": command, "timeout": timeout})
    if reply.get("error"):
        print(reply["error"], file=sys.stderr)
        return 1
    if reply.get("out"):
        sys.stdout.write(reply["out"])
        if not reply["out"].endswith("\n"):
            sys.stdout.write("\n")
    if reply.get("err"):
        sys.stderr.write(reply["err"])
        if not reply["err"].endswith("\n"):
            sys.stderr.write("\n")
    return int(reply.get("rc", 0))


def cmd_status():
    state = read_state()
    if not state:
        print("NOT RUNNING")
        return 1
    try:
        reply = rpc({"op": "ping"}, timeout=10)
    except Exception as exc:
        print("DEAD  (state file left in place, in case it is only busy: %s)" % exc)
        return 1
    idle = int(reply.get("idle", 0))
    print("RUNNING  pid=%s port=%s  %s@%s  started %s  idle %dm  (shuts down at %dm idle)"
          % (state["pid"], state["port"], state["user"], HOST,
             state["started_at"], idle // 60, IDLE_LIMIT // 60))
    return 0


def cmd_stop():
    if not read_state():
        print("NOT RUNNING")
        return 0
    try:
        rpc({"op": "stop"}, timeout=10)
    except Exception:
        pass
    clear_state()
    print("stopped")
    return 0


# --------------------------------------------------------------------- daemon --

def connect(user):
    """One interactive authentication. The password never leaves this function."""
    if paramiko is None:
        raise SystemExit("paramiko is not installed:  pip install paramiko")

    print("Connecting to %s as %s" % (HOST, user))
    print("You will be asked for your Caltech password, then Duo. Approve it on")
    print("your phone. This happens ONCE; after it, commands are free until the")
    print("daemon stops.\n")

    transport = paramiko.Transport((HOST, PORT))
    transport.start_client(timeout=30)

    # Trust on first use, then pin. Without this, paramiko accepts any host key
    # silently - which would make a machine-in-the-middle on the campus network
    # a password-harvesting exercise. ssh itself does exactly this dance; the
    # difference is that ssh has a known_hosts file already and we do not.
    key = transport.get_remote_server_key()
    fingerprint = "%s:%s" % (key.get_name(), key.get_base64())
    if KNOWN_HOST_FILE.exists():
        expected = KNOWN_HOST_FILE.read_text().strip()
        if expected != fingerprint:
            transport.close()
            raise SystemExit(
                "The host key for %s has CHANGED.\n\n"
                "  expected: %s\n  got:      %s\n\n"
                "This is either IMSS rebuilding the machine or somebody sitting\n"
                "between you and it. Do not type your password until you know\n"
                "which. If IMSS confirms a rebuild, delete\n  %s\nand run this again."
                % (HOST, expected[:60], fingerprint[:60], KNOWN_HOST_FILE))
    else:
        KNOWN_HOST_FILE.write_text(fingerprint)
        print("First connection: pinned this machine's host key (%s...).\n"
              "A change from now on will stop the daemon rather than prompt.\n"
              % fingerprint[:28])

    def handler(title, instructions, prompts):
        """Print whatever the server asks and answer from the terminal.

        Caltech asks two things in sequence - the password, then a Duo choice -
        and the second prompt's wording changes. Echoing the server's own text
        rather than guessing at it is what makes this work when they change it.
        """
        if title:
            print(title.strip())
        if instructions:
            print(instructions.strip())
        answers = []
        for prompt, echo in prompts:
            if echo:
                answers.append(input(prompt))
            else:
                answers.append(getpass.getpass(prompt))
        return answers

    try:
        transport.auth_interactive(user, handler)
    except paramiko.BadAuthenticationType:
        # Server does not offer keyboard-interactive; fall back to plain password.
        transport.auth_password(user, getpass.getpass("Caltech password: "))

    if not transport.is_authenticated():
        raise SystemExit("Authentication failed. Nothing was started.")

    client = paramiko.SSHClient()
    client._transport = transport
    print("\nauthenticated.")
    return client


def serve(client, user):
    lock      = threading.Lock()
    last_used = [time.time()]
    token     = secrets.token_hex(16)
    stopping  = threading.Event()

    listener = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        listener.bind((LISTEN_HOST, LISTEN_PORT))
    except OSError:
        raise SystemExit(
            "Port %d is already in use - another daemon is probably running.\n"
            "  python tools/portal_daemon.py --status\n"
            "  python tools/portal_daemon.py --stop" % LISTEN_PORT)
    listener.listen(8)
    listener.settimeout(5)

    write_state({
        "pid": os.getpid(), "port": LISTEN_PORT, "token": token,
        "user": user, "host": HOST,
        "started_at": time.strftime("%Y-%m-%d %H:%M:%S"),
    })

    print("READY. Leave this window open.")
    print("  status :  python tools/portal_daemon.py --status")
    print("  use    :  python tools/portal_daemon.py --run \"hostname\"")
    print("  stop   :  python tools/portal_daemon.py --stop   (or close this window)")

    def handle(conn):
        conn.settimeout(MAX_TIMEOUT + 60)
        try:
            buf = b""
            while not buf.endswith(b"\n"):
                chunk = conn.recv(65536)
                if not chunk:
                    return
                buf += chunk
            req = json.loads(buf.decode())

            if req.get("token") != token:
                conn.sendall(b'{"error": "bad token"}\n')
                return

            idle_before  = time.time() - last_used[0]
            last_used[0] = time.time()
            op = req.get("op")

            if op == "ping":
                reply = {"ok": True, "idle": idle_before}
            elif op == "stop":
                reply = {"ok": True}
                stopping.set()
            elif op == "run":
                timeout = min(int(req.get("timeout") or 120), MAX_TIMEOUT)
                # One connection, so one command at a time. Serialising here is
                # what keeps two callers from interleaving on the same channel.
                with lock:
                    stdin, stdout, stderr = client.exec_command(
                        req["cmd"], timeout=timeout, get_pty=False)
                    stdin.close()
                    out = stdout.read().decode("utf-8", "replace")
                    err = stderr.read().decode("utf-8", "replace")
                    rc  = stdout.channel.recv_exit_status()
                reply = {"rc": rc, "out": out, "err": err}
            else:
                reply = {"error": "unknown op %r" % op}
        except Exception as exc:
            reply = {"error": "%s: %s" % (type(exc).__name__, exc)}
        try:
            conn.sendall((json.dumps(reply) + "\n").encode())
        except OSError:
            pass
        finally:
            conn.close()

    try:
        while not stopping.is_set():
            try:
                conn, _ = listener.accept()
            except socket.timeout:
                if time.time() - last_used[0] > IDLE_LIMIT:
                    print("idle for %d hours - shutting down." % (IDLE_LIMIT // 3600))
                    break
                continue
            threading.Thread(target=handle, args=(conn,), daemon=True).start()
    except KeyboardInterrupt:
        print("\ninterrupted.")
    finally:
        clear_state()
        listener.close()
        try:
            client.close()
        except Exception:
            pass
        print("daemon stopped; the ssh session is closed.")


def main():
    ap = argparse.ArgumentParser(
        description="Hold one authenticated ssh session to portal.caltech.edu.")
    ap.add_argument("--status", action="store_true", help="is it running")
    ap.add_argument("--stop",   action="store_true", help="shut it down")
    ap.add_argument("--run",    metavar="CMD", help="run CMD on the server")
    ap.add_argument("--timeout", type=int, default=120,
                    help="seconds to allow --run (default 120, max %d)" % MAX_TIMEOUT)
    args = ap.parse_args()

    if args.status:
        return cmd_status()
    if args.stop:
        return cmd_stop()
    if args.run:
        return cmd_run(args.run, args.timeout)

    # A state file does not prove a daemon. Closing the window kills the process
    # without running its cleanup, and a leftover file that refuses every future
    # start - with no hint about how to recover - is the worst possible failure
    # for something meant to be double-clicked. So ask it, and only believe it
    # if it answers.
    if read_state():
        try:
            rpc({"op": "ping"}, timeout=5)
        except Exception:
            print("Clearing a stale state file (the last daemon did not shut down "
                  "cleanly - closed window, or a reboot).")
            clear_state()
        else:
            print("A daemon is already running, and answering. Nothing to do.")
            print("  --status to see it, --stop to close it.")
            return 0

    user = username()
    client = connect(user)
    serve(client, user)
    return 0


if __name__ == "__main__":
    sys.exit(main())

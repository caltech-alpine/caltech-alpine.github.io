# Getting access to the server

Read this when you take over the website, or when a second officer needs to be
able to deploy. It ends with how to hand access back when you leave, which is
the part that usually gets skipped.

Written 2026-08-18, from doing it. The mistakes listed here are ones that were
actually made that day, not hypotheticals.

---

## What you need before anything works

**A Caltech account** — the access.caltech one. Yours, not a shared club login.

**Membership of the `alpinewww` group.** This is what lets you write to the
site's folder. As of 2026-08-18 it has four members: `khunady`, `mpfreema`,
`zauvil`, `mhannah`. To add somebody, open a ticket at
[help.caltech.edu](https://help.caltech.edu) naming the person and the site.
Check who is in it with:

```bash
getent group alpinewww
```

**The Caltech VPN, on the "Tunnel All" profile.** `portal.caltech.edu` is not
reachable from off campus otherwise, and it is not reachable on the default
split-tunnel profile either. The failure looks like the server being down rather
than like a VPN problem, which wastes an afternoon if you do not know.

---

## The quick version, if you are on macOS or Linux

```bash
ssh-keygen -t ed25519 -f ~/.ssh/caltech-portal -C "your-name-portal"
ssh-copy-id -i ~/.ssh/caltech-portal.pub YOUR_USERNAME@portal.caltech.edu
```

Give it a passphrase when asked. Then add to `~/.ssh/config`:

```
Host portal
    HostName portal.caltech.edu
    User YOUR_USERNAME
    IdentityFile ~/.ssh/caltech-portal
    IdentitiesOnly yes
```

`ssh portal` now works, and so does `tools/deploy.sh`. Skip to *When you leave*.

---

## Windows, with PuTTY

Most of the club runs Windows, and PuTTY is the usual tool. It works, with two
traps.

### 1. Make the key

Open **PuTTYgen**. Set **Key type** to **EdDSA / Ed25519**, click **Generate**,
and move the mouse until the bar fills.

- **Set a passphrase.** This key is your Caltech account, not a website login.
  Anything that can read an unprotected key file can log in as you.
- **Copy the text out of the top box**, the one labeled *Public key for pasting
  into OpenSSH authorized_keys file*.

> ⚠ **Do not use the "Save public key" button.** It writes a different format
> (RFC 4716, several lines, with a header) which `authorized_keys` rejects
> without saying so. You get a key that looks installed and never works. The
> one-line text in the top box is the format the server wants.

- **Save private key** to `C:\Users\YOU\.ssh\caltech-portal.ppk`.

### 2. Install the public half on the server

Log in with your password one last time — PuTTY, or from Git Bash:

```bash
ssh YOUR_USERNAME@portal.caltech.edu
```

Then paste this, with your copied line in place of `PASTE_HERE`:

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh && echo 'PASTE_HERE' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && cat ~/.ssh/authorized_keys
```

The permissions are not decoration. sshd ignores `authorized_keys` if the file
or the directory is writable by anyone else, and it does so silently.

> ⚠ **Do not put `restrict` in front of your key.** It is a useful option for a
> key that only ever runs single commands, because it disables port forwarding,
> agent forwarding and pty allocation — but no pty means no interactive shell,
> so PuTTY will connect and give you nothing.

### 3. Set up the PuTTY session

| Where in PuTTY | What to put |
|---|---|
| Session → Host Name | `portal.caltech.edu` |
| Connection → Data → Auto-login username | your Caltech username |
| Connection → SSH → Auth → Credentials → Private key file | your `.ppk` |
| Session → Saved Sessions | type `portal`, click **Save** |

Double-clicking `portal` now logs in with the key passphrase and no Duo prompt.
Duo gates password authentication, not public keys.

### 4. Pageant, so you type the passphrase once

Run **Pageant**, right-click its tray icon, **Add Key**, choose the `.ppk`. It
holds the passphrase until you log out. For once per boot rather than once per
session, put a shortcut in your Startup folder with the key as an argument:

```
"C:\Program Files\PuTTY\pageant.exe" "C:\Users\YOU\.ssh\caltech-portal.ppk"
```

### 5. The step people forget: deploying needs the other key format

`tools/deploy.sh` runs `ssh` and `tar`, not PuTTY, so a `.ppk` alone leaves you
typing a password on every deploy. In PuTTYgen, load your `.ppk` and use
**Conversions → Export OpenSSH key**, saved as `C:\Users\YOU\.ssh\caltech-portal`
with no extension. Then add to `C:\Users\YOU\.ssh\config`:

```
Host portal
    HostName portal.caltech.edu
    User YOUR_USERNAME
    IdentityFile ~/.ssh/caltech-portal
    IdentitiesOnly yes
```

Run deploys from **Git Bash**, not cmd or PowerShell.

---

## What does not work here

**SSH connection sharing (`ControlMaster`) does not work from Git Bash.** It
looks like it does: the socket appears and `ssh -O check` reports the master
running. Then every actual session is refused, because the Unix-socket emulation
cannot pass file descriptors between processes, which is what multiplexing
needs. Windows' own OpenSSH does not implement sharing at all. Use a key.

**There is no PHP and no web server on `portal`.** It is a file server. Anything
in the documentation that says to run `php tools/check.php` on the server is
wrong; see [SERVERS.md](SERVERS.md).

---

## When you leave

Do these three things, in this order, and tell the next person you did.

**1. Remove your key from the server.** Note the `sed`, not a `grep -v` pipeline:
if your key is the only line in the file, `grep -v` matches nothing, exits
non-zero, and an `&&` chain after it silently does nothing at all. That mistake
was made on 2026-08-18 and was only caught by testing the lockout afterwards.

```bash
sed -i '/your-key-comment/d' ~/.ssh/authorized_keys
```

**2. Check it actually worked.** A revocation you did not verify is not a
revocation:

```bash
ssh -o BatchMode=yes YOUR_USERNAME@portal.caltech.edu 'id'
```

`Permission denied (publickey,password)` is the answer you want.

**3. Ask IMSS to take you out of `alpinewww`**, and make sure at least two people
are still in it. One name in that group is how a club loses its website: the
person graduates, nobody else can write to the folder, and the site freezes in
whatever state it was left in until somebody opens a ticket that nobody knows to
open.

Update the member list at the top of this file while you are at it, so the next
person is not reading a roster from three years ago.

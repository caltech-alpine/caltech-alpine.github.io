# Deploy log

One entry per deployment or per attempt at one. Newest at the top. The failures
are the valuable part: the next person to do this will be doing it for the first
time, and every wasted hour recorded here is one they do not spend.

Format: date, who, what was deployed, what happened. Paste in the actual output
rather than describing it.

---

## 2026-08-18 (later) - logged into portal, and the procedure changed

**Who:** Kyle, with Claude driving read-only commands over an SSH key. **Deployed:** still nothing.

The docroot is **`/srv/www.alpine.caltech.edu/www/docroot/`** - one folder, named
for production rather than staging, created 2026-08-17 15:45, owner `khunady`,
group `alpinewww`, mode `2775` with the setgid bit already set by IMSS. Empty.

Four things came out of the session:

1. **`alpinewww` has four members**: `khunady`, `mpfreema`, `zauvil`, `mhannah`.
   Three other people can already deploy this site.
2. **portal has no PHP and no Apache.** RHEL 9.8, nothing listening on 80 or 443.
   The site is served by Apache 2.4.65 on Debian - a different machine.
3. **`/srv` is an AWS EFS share.** Files are written here and read there.
4. **So `php tools/check.php` cannot be run at all**, on either machine.
   `DEPLOY.md` step 4 rewritten to say so instead of instructing something
   impossible.

Still open: which hostname this docroot serves. It is named
`www.alpine.caltech.edu`, but `alpine.caltech.edu` currently returns the Wagtail
site through Cloudflare, so it is not being served there. The probe upload
settles it.

**Access note.** SSH connection sharing (`ControlMaster`) does not work from Git
Bash - the Unix-socket emulation cannot pass file descriptors, so `-O check`
succeeds and an actual session is refused. Three failed password attempts were
logged against the account before that was understood. Replaced with a dedicated
ed25519 key, `~/.ssh/alpine-portal`, installed with the `restrict` option, host
alias `alpine-portal`. **Revoke it when the deploy is proven** - it is full
account access without Duo for as long as the line sits in `authorized_keys`.

## 2026-08-18 - staging provisioned, nothing uploaded yet

**Who:** Kyle. **Deployed:** nothing.

IMSS provisioned `staging.alpine.caltech.edu` under ticket **INC0028327**
(Danny Caballero, comment at 15:15 PDT). Uploads go through
`portal.caltech.edu`, following
<https://www.imss.caltech.edu/services/web-hosting-development/web-hosting/managing-your-dynamic-web-site>.

Checked what could be checked from outside, off campus, before touching
anything. Full detail in [SERVERS.md](SERVERS.md).

- The hostname resolves, through Cloudflare, and HTTPS already works. The
  certificate was issued at 20:50 UTC the same day and renews itself.
- The origin identifies itself as `Apache/2.4.65 (Debian) ... Port 80`. Apache
  means `.htaccess` should be read. Port 80 means TLS is terminated in front of
  it, which is why a force-HTTPS rule must never be added here.
- The document root is **empty**: the server returns its own directory listing
  with nothing in it.

Nothing was uploaded, because the upload needs the VPN on Tunnel All and the
document root path is still a guess (`/srv/www.staging.alpine.caltech.edu/www/docroot/`,
from the IMSS naming pattern, not yet seen).

**Baseline from `tools/verify_deploy.py`**, run against the empty document root
so that there is something to compare the first real deploy against:

```
  ok    GET /                                    HTTP 200, 568 bytes  (Apache's own listing)
  FAIL  GET /index.php ... /sitemap.php          HTTP 404             (nothing uploaded)
  FAIL  home page is not a directory listing     empty document root
  FAIL  security headers arrive (.htaccess read) 0 of 3 present
  --    denied /includes/, /data/, /cache/       HTTP 404, proves nothing yet
  ok    missing page returns 404                 HTTP 404
  FAIL  404 page is ours, not Apache's default   288 bytes
  FAIL  this copy tells search engines away      X-Robots-Tag: (none)

  20 checks, 12 failed, 5 could not be told
```

Every one of those failures is expected on an empty document root. After the
first upload they should all turn green except possibly the `.htaccess` row,
which depends on whether IMSS has `AllowOverride` on.

⚠ `tools/probe.php` has **never been run through a PHP interpreter** - there is
no PHP on the machine it was written on. If it returns a 500, suspect the probe
before suspecting the server.

**Written while this was fresh:** [DEPLOY.md](DEPLOY.md) (the procedure),
[SERVERS.md](SERVERS.md) (the machines), `tools/probe.php` (the one file to
upload first), `tools/deploy.sh` (the upload), `tools/verify_deploy.py` (the
check afterwards).

**Next:** on the VPN with Tunnel All, run DEPLOY.md steps 1 and 2 - find the
real document root, upload the probe, read it, paste the result here.

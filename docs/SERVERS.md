# Where this site lives

Every machine, hostname and path involved in serving the Alpine Club site, and
how each fact was checked. Facts about servers rot faster than anything else in
this repository, so each line carries the date it was measured and the command
that measured it. If you are reading this a year from now, re-run the commands
before trusting a number.

Last measured **2026-08-18**.

---

## The three copies of the site

| | Production | Staging | Pages pilot |
|---|---|---|---|
| Address | https://alpine.caltech.edu | https://staging.alpine.caltech.edu | https://caltech-alpine.github.io |
| What runs there | Caltech Sites (Wagtail CMS) | Apache + PHP, our files | Static HTML built by GitHub Actions |
| Who controls the content | Caltech Sites editors | us, over SFTP | this repository, `main` branch |
| Indexed by search engines | yes | no, and it must stay that way - `.htaccess` sends `X-Robots-Tag` when the Host starts `staging.` | no (`noindex`) |
| Status | live, untouched by any of this | **live, serving the PHP site** (verified 2026-08-19) | live |

Only production is the club's real site. Nothing done on staging changes it.

---

## Staging: staging.alpine.caltech.edu

Provisioned by IMSS on 2026-08-18 under ticket **INC0028327**, set up by **Danny
Caballero** (IMSS). His note: the Alpine staging site is available for the site
files to be published, through the `portal.caltech.edu` file server.

### What we can see from outside

Measured 2026-08-18, from off campus, before anything was uploaded.

**DNS** — `nslookup staging.alpine.caltech.edu`

```
staging.alpine.caltech.edu
  CNAME  staging.alpine.caltech.edu.cdn.cloudflare.net
  A      104.18.39.119, 172.64.148.137
  AAAA   2606:4700:4406::6812:2777, 2606:4700:440b::ac40:9489
```

The site sits behind Cloudflare. `alpine.caltech.edu` resolves to the same two
IPv4 addresses, but that means nothing on its own, because those are shared
Cloudflare addresses used by thousands of sites. The two hostnames reach
different origin servers, which the next section shows.

**Certificate** — `openssl s_client -connect staging.alpine.caltech.edu:443`

```
subject  CN = staging.alpine.caltech.edu
issuer   Google Trust Services, CN = WE1
valid    2026-08-18 20:50 UTC  to  2026-11-16 21:50 UTC
```

A 90-day certificate issued at the edge about an hour before IMSS said the site
was ready. It renews itself. Nobody at the club has to do anything about HTTPS,
and no certificate or key belongs anywhere in this repository.

**Origin server** — `curl https://staging.alpine.caltech.edu`

```html
<h1>Index of /</h1>
<address>Apache/2.4.65 (Debian) Server at staging.alpine.caltech.edu Port 80</address>
```

Four things come out of that one line.

1. Apache, not nginx. Our `.htaccess` will be read, assuming `AllowOverride` is
   on. That is the biggest open question, and the probe in [DEPLOY.md](DEPLOY.md)
   answers it.
2. Debian, Apache 2.4.65, current at the time of writing.
3. The document root is empty. Apache fell through to a directory listing
   because there is no `index.php` yet. Uploading the site removes the listing,
   and `Options -Indexes` in our `.htaccess` stops it coming back elsewhere.
4. Port 80. The origin speaks plain HTTP and TLS is terminated in front of it.
   See the warning below, because this one can take a site down.

The response also carries `Server: cloudflare` and `AWSALB` cookies, so the path
is roughly: visitor, then Cloudflare, then an AWS load balancer, then the Apache
host.

### Be careful forcing HTTPS in `.htaccess` on this host

The origin listens on port 80 and TLS is terminated in front of it, which is the
classic setup for an infinite redirect loop: a rule testing `%{HTTPS}` never
sees HTTPS, so it redirects a browser to the address it is already on, forever.

**Measured 2026-08-18, and it is better than that.** The probe reported
`$_SERVER['HTTPS']` set and `X-Forwarded-Proto: https`, so this server is
configured to honor the proxy's header rather than ignore it. A `%{HTTPS}` rule
might therefore work here. Might is not good enough to bet the site on, and
`mod_rewrite` does not necessarily see the same value PHP does.

So: test the header directly. It is correct whether or not the server is
translating it:

```apache
RewriteCond %{HTTP:X-Forwarded-Proto} =http
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

None of this is necessary today. The edge already serves HTTPS and our
`.htaccess` has no redirect rules in it. The rule worth keeping is to leave it
that way.

### The file server

Uploads go to **`portal.caltech.edu`** (52.25.203.203, an AWS address), with a
Caltech account, over SCP/SFTP or an interactive SSH shell. It is reachable only
**from campus, or over the Caltech VPN with the "Tunnel All" option**. Split
tunnelling fails to connect, and the failure looks like the server being down.

**Verified on the server, 2026-08-18.** There is one folder, not two, and it
is named for production rather than for staging:

```
/srv/www.alpine.caltech.edu/www/docroot/     owner khunady, group alpinewww, mode 2775
```

`/srv/` holds 185 of these, one per Caltech site, and `www.alpine.caltech.edu`
is the only one we can see. It was created 2026-08-17 15:45, the day before IMSS
said staging was ready.

**So one thing is still open: which hostname this docroot serves.** It is named
for `alpine.caltech.edu`, but `alpine.caltech.edu` currently resolves to
Cloudflare and returns the Wagtail site, so it is not being served there today.
The probe upload settles it — if `_probe.php` appears at
`staging.alpine.caltech.edu`, this is staging, and this file gets that written
into it rather than assumed. Until then, treat anything put here as potentially
one IMSS change away from being public.

IMSS asks for **0664 on files and 2775 on folders**. The setgid bit on the
folder is what lets the next officer edit what this one uploaded, and IMSS has
already set it on the docroot, so files created inside inherit `alpinewww`
without anybody having to remember. Getting it wrong does not break the site; it
locks out everyone except the person who uploaded.

### portal is a file server. The web server is a different machine.

Checked over SSH on 2026-08-18, and it changes the deploy procedure.

| | |
|---|---|
| `portal.caltech.edu` | Red Hat Enterprise Linux 9.8 |
| PHP on it | **none.** `php` is not installed |
| Apache on it | **none.** Nothing is listening on 80 or 443; the only listeners are ssh, rpcbind, local mail and a monitoring agent |
| `/srv` | an **AWS EFS share** (`fs-2505c98c.efs.us-west-2.amazonaws.com:/userdata`), 2.0 TB used |
| The host actually serving the site | Apache 2.4.65 on **Debian**, per the HTTP response. RHEL here, Debian there: they are certainly not the same machine |

So the shape is: you write files onto a shared network filesystem from a RHEL
box, and a separate Debian web tier reads them and serves them. We have a shell
on the first and none on the second.

**The consequence is that `php tools/check.php` cannot be run anywhere today.**
It is a command-line script, the only machine we can log into has no PHP, and
the machine with PHP takes HTTP requests and nothing else. [DEPLOY.md](DEPLOY.md)
step 4 says so; `tools/probe.php` and `tools/verify_deploy.py` cover most of what
it would have told us, both over HTTP.

**Who can deploy** — `alpinewww` has four members: `khunady`, `mpfreema`,
`zauvil`, `mhannah`. So the succession question the redesign notes raised has a
real answer rather than a hopeful one: three other people already hold the
access, and the setgid bit on the docroot means files one of them uploads stay
editable by the others.

### What the probe found, 2026-08-18

`_probe.php` was uploaded to the docroot and answered at
`staging.alpine.caltech.edu` but **404 at `alpine.caltech.edu`**. That settles
it: this document root is staging today, production is still Wagtail, and the
two are not the same server despite the folder's name.

| | |
|---|---|
| PHP | **8.2.29**, running as `fpm-fcgi` |
| cURL | available |
| `allow_url_fopen` | on |
| Reaching Google | **HTTP 200 from calendar.google.com** — outbound is not firewalled, so the calendar will work |
| Document root | `/srv/www.alpine.caltech.edu/www/docroot` — the folder we upload to |
| The web server runs as | **`www-data`** |
| Writing to the docroot | **no** |
| Request scheme seen by PHP | HTTPS, `X-Forwarded-Proto: https` |

**The one failure is the one that matters.** Apache runs as `www-data`, our files
are owned by `khunady:alpinewww` at mode 2775, and `www-data` is not in
`alpinewww`, so the site cannot write its calendar cache. Nothing breaks: the
code falls back to calling Google on every single page load, which is slow and
rude to Google, but it serves. Two ways to fix it, in order of preference:

1. **Ask IMSS to add `www-data` to `alpinewww`**, or to tell us which group the
   web tier can write as. This is the clean answer and it is one ticket. **Open
   as of 2026-08-18.**
2. **`chmod 3777 cache logs`**, which `tools/deploy.sh` now does on every deploy.
   World writable, plus the sticky bit so only a file's owner can delete it. Both
   directories are denied over HTTP by their own `.htaccess`. A workaround, not a
   fix — revert it to `2775` once the ticket is answered.

**A third option was ruled out.** POSIX ACLs would have granted the web server
alone, with `setfacl -m u:www-data:rwx`. They do not work here. `www-data` is a
Debian username and does not exist in `portal`'s password file at all, since the
two machines share no user list; and using the numeric UID instead fails anyway,
because `setfacl` returns `Operation not supported` on this filesystem. Amazon
EFS does not implement POSIX ACLs.

### Still unknown

- Whether `AllowOverride` is on, so `.htaccess` is read. Only a real deploy
  answers this, because the probe is a single file with no `.htaccess` beside it
- Whether the club can have `alpine.caltech.edu` repointed here, and what
  happens to this docroot when it is
- Whether IMSS backs up this document root, and for how long
- Whether the staging hostname can later be repointed, or whether production
  means a second document root behind `alpine.caltech.edu`
- Whether IMSS keeps backups of this document root, and for how long

---

## Production: alpine.caltech.edu

Runs **Caltech Sites**, the Institute's Wagtail platform, with assets served
from `caltechsites-prod-assets.resources.caltech.edu`. Content is edited in a
browser by whoever holds the login. Nothing in this repository is deployed
there, and nothing here can break it.

Moving the club onto our own files is a decision, not a deployment. See
[HOSTING.md](HOSTING.md). Until that decision is made and IMSS repoints
something, production is the Wagtail site and staging is a preview nobody
outside the club needs to see.

---

## The Pages pilot: caltech-alpine.github.io

A static render of this repository, built by `.github/workflows/pages.yml` every
30 minutes so the calendar stays current, marked `noindex` so it cannot compete
with the real site in search results. It exists so the committee could look at
the redesign without any Caltech infrastructure being involved. Keep it or drop
it once staging works; `HOSTING.md` §4 has the rules if both stay up.

GitHub disables scheduled workflows in a repository that sees no commits for 60
days, and notifies only the last person who committed. If the pilot's calendar
ever freezes, look at the Actions tab before looking at the code.

---

## The workstation this gets deployed from

Kyle's machine, measured 2026-08-18. Recorded because two things are missing
from it and the deployment instructions have to work around them.

| | |
|---|---|
| OS | Windows 11 Pro, build 26200 |
| Shell used for deploys | Git Bash |
| SSH | OpenSSH 10.3p1. `scp` in this version speaks the SFTP protocol, so it works even against an SFTP-only server |
| Also installed | PuTTY (`psftp`, `pscp`, `plink`), git 2.54.0, Python 3.14.6, GitHub CLI |
| **No `rsync`** | The one-line `rsync` deploy in the older notes cannot run here. `tools/deploy.sh` uses `scp` instead |
| **No PHP** | The site cannot be run on this machine. `php -S 127.0.0.1:8800`, `tools/check.php` and `tools/audit.py` all need PHP, so for now they run **on the server** |

Installing PHP locally would allow testing before uploading, which is worth
doing before the production cutover. Until then, staging is the test.

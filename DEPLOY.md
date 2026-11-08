# Deploy your portfolio on Vercel (with the PHP contact form)

This folder is ready to upload. Your site keeps its design; only the contact form's back end changed so it works on Vercel.

## What changed and why

Vercel has no built-in PHP and no mail server, and its disk is read-only. So the old `contact.php` (which wrote to `messages.txt`) could not work there. This version:

- runs PHP on Vercel with the community runtime **vercel-php** (pinned in `vercel.json`),
- sends the message to your Gmail through **SMTP with PHPMailer** (the standard PHP mail library),
- keeps your Gmail password out of the code (it goes in Vercel's Environment Variables).

```
vercel-portfolio/
├─ public/            your website: index.html, style.css, script.js, assets/, CV pdf
├─ api/contact.php    the PHP contact form handler  ->  /api/contact.php
├─ lib/phpmailer/     PHPMailer 7.1.1 (official, bundled so nothing needs installing)
├─ vercel.json        tells Vercel: serve public/, run api/*.php with vercel-php
├─ package.json       pins Node 22 (vercel-php needs it; Vercel's default is newer)
├─ router.php         only for testing on your own computer
└─ .env.example       the names of the settings you will add in Vercel
```

`index.php` became `public/index.html`. Its only PHP was the footer year, and `script.js` already sets that, so the page loads as a fast static file. The PHP part is the contact form.

---

## Step 1. Make a Gmail app password (5 minutes)

The form logs in to Gmail to send the email. Google requires an *app password* for this (your normal password will not work).

1. Sign in to the Gmail account that should **receive** the messages (llanesvincelouie@gmail.com).
2. Turn on **2-Step Verification**: <https://myaccount.google.com/security>
3. Open <https://myaccount.google.com/apppasswords>, name it `Portfolio`, press **Create**.
4. Copy the 16 letters. Remove the spaces. Keep them for Step 3.

If the app passwords page is missing, 2-Step Verification is not on yet, or the account is a school/work account that blocks it.

## Step 2. Put the project on GitHub

**Easiest (in the browser)**

1. Go to <https://github.com/new>, name it `portfolio` (or anything), press **Create repository**.
2. Press **uploading an existing file**.
3. Open the unzipped folder and drag its **contents** in (the `public`, `api`, `lib` folders and the loose files), not the outer folder itself.
4. Press **Commit changes**.

Check that the repo root shows `vercel.json`, `api/`, `public/` and `lib/`. If they are inside another folder, Vercel will not find them.

**Or with Git**

```
cd vercel-portfolio
git init
git add .
git commit -m "Portfolio for Vercel"
git branch -M main
git remote add origin https://github.com/vincellanes/portfolio.git
git push -u origin main
```

Never upload a `.env` file. `.gitignore` already blocks it.

## Step 3. Import it into Vercel

1. Sign in at <https://vercel.com/new> with **Continue with GitHub**.
2. Press **Import** next to your repo.
3. On the *Configure Project* screen:
   - **Framework Preset:** `Other`
   - **Root Directory:** `./`
   - Leave Build Command, Output Directory and Install Command alone (`vercel.json` sets them).
4. Open **Environment Variables** and add these (keep all environments ticked):

   | Name | Value |
   |---|---|
   | `SMTP_USER` | your Gmail address, e.g. `llanesvincelouie@gmail.com` |
   | `SMTP_PASS` | the 16-letter app password from Step 1, no spaces |
   | `MAIL_TO` | *(optional)* a different inbox to deliver to. Default is `SMTP_USER` |

   If you see a **Sensitive** option for `SMTP_PASS`, turn it on.
5. Press **Deploy**. It takes about a minute. Then open the `https://….vercel.app` link.

> Environment variables only apply to **new** deployments. If you add or change one later, go to *Deployments* → the latest one → **⋯** → **Redeploy**.

## Step 4. Test the form

1. On your live site, scroll to **Get in touch**, fill in the form and press **Send message**.
2. You should see *"Message sent."* and the email in your Gmail inbox a few seconds later. Check **Spam** once. If it is there, press **Not spam**.
3. Press **Reply** in Gmail. The reply goes to the visitor, because the form sets Reply-To.

## If something goes wrong

The form shows the reason in brackets. For details, open your project in Vercel → **Logs** and look at `/api/contact.php`.

| What you see | Cause | Fix |
|---|---|---|
| `(the mail service is not set up yet)` | `SMTP_USER` / `SMTP_PASS` missing, or added after the last deploy | Add them in Settings → Environment Variables, then **Redeploy** |
| `(the mail server could not be reached right now)` and Logs say `Username and Password not accepted` | Wrong app password, typo in the address, or 2-Step Verification is off | Make a new app password, paste it again with no spaces, Redeploy |
| `404` on `/api/contact.php` | `vercel.json` or `api/` is not at the repo root | Move the files up so they are at the root, or set Root Directory correctly |
| Whole site is `404` | `public/` folder is missing at the repo root | Re-upload so `public/index.html` exists |
| Build error mentioning Node or `vercel-php` | Project overrides the Node version | `package.json` pins Node 22.x. In Settings → Build and Deployment → Node.js Version choose 22.x |
| Page works, no email, no error | Landed in Spam | Check Spam, press **Not spam** |

## Later changes

- **Update the site:** change files in GitHub (or push with Git). Vercel redeploys by itself.
- **Custom domain:** Project → Settings → Domains.
- **Test on your computer:** with PHP installed, run
  `SMTP_USER=you@gmail.com SMTP_PASS=yourapppassword php -S localhost:8000 router.php` and open <http://localhost:8000>.

## Good to know

- `vercel-php` is a community runtime, not made by Vercel. It is pinned to `vercel-php@0.9.0` (PHP 8.5) so a new release cannot change your site unexpectedly.
- The form has a hidden bot trap, rejects invalid or very long input (message limit 5000 characters) and strips line breaks from names and emails so nobody can inject extra email headers.
- Vercel's free Hobby plan is for personal, non-commercial projects, which fits a student portfolio.

---

*Prepared by Claude · Prompted by Cien*

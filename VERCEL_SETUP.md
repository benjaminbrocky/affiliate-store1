# Vercel Deployment Setup Guide

## Step 1: Install Composer Dependencies (do this once locally)

Before pushing to GitHub, run this in the project folder:

```bash
composer install
```

This generates the `vendor/` folder. Since `vendor/` is now included in the repo, commit it too.

---

## Step 2: Add Environment Variables in Vercel

Go to your Vercel project → **Settings → Environment Variables** and add each of the following:

| Variable | Example Value |
|---|---|
| `DATABASE_URL` | `mysql://username:password@aws.connect.psdb.cloud/dbname?sslaccept=strict` |
| `AMAZON_ACCESS_KEY` | your Amazon access key |
| `AMAZON_SECRET_KEY` | your Amazon secret key |
| `AMAZON_ASSOCIATE_TAG` | your-tag-20 |
| `AMAZON_REGION` | com |
| `GEMINI_API_KEY` | your Gemini API key |
| `GEMINI_MODEL` | gemini-1.5-pro |
| `SITE_NAME` | GearGuide |
| `SITE_URL` | https://your-app.vercel.app |
| `SITE_DESCRIPTION` | Expert reviews and buying guides |
| `ADMIN_PASSWORD` | a strong password |
| `ADMIN_EMAIL` | your email |
| `AUTO_BLOG_ENABLED` | true |
| `AUTO_BLOG_INTERVAL_HOURS` | 24 |
| `POSTS_PER_CATEGORY` | 3 |
| `MIN_WORD_COUNT` | 800 |
| `MAX_WORD_COUNT` | 1500 |
| `AMAZON_SEARCH_INDICES` | Electronics,HomeGarden,Sports,PetSupplies,Kitchen |

> ⚠️ Do NOT rely on the `.env` file for production — Vercel ignores it. Use the dashboard above.

---

## Step 3: Database

Vercel has no built-in database. Use a hosted MySQL service:
- **PlanetScale** (free tier, recommended) — https://planetscale.com
- **Railway** — https://railway.app
- **Aiven** — https://aiven.io

Import `database.sql` into whichever service you choose, then paste the connection URL as `DATABASE_URL`.

---

## Changes Made to Fix Deployment

1. `config.php` — changed `$dotenv->load()` to `$dotenv->safeLoad()` so it doesn't crash when `.env` is absent on Vercel
2. `.gitignore` — removed `vendor/` and `composer.lock` so dependencies are committed and available on Vercel

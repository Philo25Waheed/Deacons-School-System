# Full-Stack Deployment Guide (Render + Vercel)

This guide provides step-by-step instructions to deploy your **Laravel 13 Full-Stack Application** using:
- **Backend**: Render Native PHP Web Service (**Free Plan**, No Docker)
- **Frontend**: Vercel Edge CDN (**Free Hobby Plan**)

---

## 🏗️ Architecture Summary

| Component | Platform | Plan | Build Technology |
| :--- | :--- | :--- | :--- |
| **Backend API / PHP** | Render | Free | Native PHP 8.3/8.4 + Composer |
| **Frontend Assets & Proxy** | Vercel | Free | Global CDN + Vercel Rewrites |
| **Database** | Render Postgres / Aiven / SQLite | Free | MySQL / PostgreSQL / SQLite |

---

## 🚀 Part 1: Deploy Backend to Render (Free Plan, No Docker)

### Step 1: Push Code to GitHub
Ensure your repository is pushed to GitHub (either public or private).

### Step 2: Create Web Service on Render
1. Log in to [Render Dashboard](https://dashboard.render.com/).
2. Click **New +** -> **Blueprint** OR **Web Service**.
   - *Option A (Blueprint - Recommended)*: Connect your GitHub repository. Render will automatically detect the [`render.yaml`](file:///e:/Deacons%20School%20System/render.yaml) file and configure the service.
   - *Option B (Manual Web Service)*:
     - Select **Native / PHP** environment (No Docker needed).
     - **Build Command**: `composer install --no-dev --optimize-autoloader`
     - **Start Command**: `php artisan serve --host 0.0.0.0 --port $PORT`
     - **Instance Type**: **Free** (512 MB RAM).

### Step 3: Configure Render Environment Variables
In the Render Web Service settings (**Environment** tab), add the following environment variables:

| Key | Value | Notes |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Production mode |
| `APP_DEBUG` | `false` | Disable debug output |
| `APP_KEY` | *(Click Generate or run `php artisan key:generate`)* | Base64 application key |
| `APP_URL` | `https://<your-render-app>.onrender.com` | Your Render URL |
| `FRONTEND_URL` | `https://<your-vercel-app>.vercel.app` | Your Vercel URL |
| `CORS_ALLOWED_ORIGINS` | `https://<your-vercel-app>.vercel.app` | Allowed CORS origins |
| `DB_CONNECTION` | `mysql` (or `pgsql` or `sqlite`) | Database type |
| `DB_HOST` | `<db-host-url>` | Database host |
| `DB_PORT` | `3306` (or `5432`) | Port |
| `DB_DATABASE` | `deacons_db` | Database name |
| `DB_USERNAME` | `<db-user>` | DB user |
| `DB_PASSWORD` | `<db-pass>` | DB password |

---

## 🌐 Part 2: Deploy Frontend to Vercel (Free Hobby Plan)

### Step 1: Connect Repository to Vercel
1. Log in to [Vercel Dashboard](https://vercel.com/dashboard).
2. Click **Add New...** -> **Project**.
3. Select your GitHub repository.

### Step 2: Framework & Build Settings
- **Framework Preset**: **Other**
- **Root Directory**: `./` (Default)
- **Build Command**: *(Leave empty or `npm run build` if Vite assets are generated)*
- **Output Directory**: `public` (or default)

### Step 3: Configure Vercel Rewrites in `vercel.json`
Update [`vercel.json`](file:///e:/Deacons%20School%20System/vercel.json) destination URL with your exact Render backend URL:
```json
{
  "source": "/api/(.*)",
  "destination": "https://<your-render-app>.onrender.com/api/$1"
},
{
  "source": "/(.*)",
  "destination": "https://<your-render-app>.onrender.com/$1"
}
```

### Step 4: Deploy
Click **Deploy**. Vercel will deploy your static assets to its high-speed global CDN and forward API & PHP requests seamlessly to your Render backend.

---

## 🗄️ Part 3: Free Database Options

1. **SQLite (Built-in Fallback)**:
   - The application automatically falls back to `database/database.sqlite` or `/tmp/database.sqlite` if no MySQL/PostgreSQL host is supplied.
2. **Render PostgreSQL (Free Tier)**:
   - Create a Free PostgreSQL database instance directly inside Render Dashboard.
   - Copy the External/Internal Connection String into your Render Environment variables (`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
3. **Aiven / Supabase / PlanetScale (Free MySQL/PostgreSQL)**:
   - Easily connect any external free MySQL or PostgreSQL instance using the `DB_*` environment variables.

---

## ✅ Part 4: Verification & Testing

1. **API CORS Verification**:
   - Open your Vercel frontend URL in your browser (`https://<your-vercel-app>.vercel.app`).
   - Test logging in, dynamic cascading dropdowns (`api/get_grades.php`), and QR attendance scanning.
2. **Backend Health Check**:
   - Visit `https://<your-render-app>.onrender.com/up` to verify Laravel backend health status.

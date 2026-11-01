# 🤖 AI Job Application Tracker

A smart, automated job-hunting assistant and application tracker built with **Laravel 11**. Instead of manually updating application statuses, the system automatically fetches recruiter responses via IMAP, categorizes them, and uses **Google Gemini AI** to act as a strict career mentor, analyzing rejection patterns and suggesting targeted resume improvements.

## ✨ Key Features

* **Automated Email Sync:** Securely connects to mailboxes via IMAP (with chunking) to fetch responses, interview invites, and rejections directly from HRs.
* **Smart AI Mentorship:** Uses Gemini AI to analyze rejection reasons, compares them against your category-specific resumes, and generates actionable, non-redundant strategy advice.
* **AI Memory & Optimization:** The AI remembers its previous advice, tracks if you've updated your resume, and only processes new data to save API limits.
* **Professional Admin Dashboard:** A dense, informative list view powered by **Filament v3**, featuring category tabs, status badges, and intelligent UI components.
* **Background Processing:** IMAP synchronization and AI parsing are decoupled into robust background Jobs/Workers to ensure fast response times.
* **Self-Hosted Infrastructure:** Fully containerized with Docker Compose, featuring automated task scheduling and persistent queues for 24/7 operation.

## 🛠️ Tech Stack

* **Backend:** PHP 8.3+, Laravel 11
* **Admin Panel:** Filament v3
* **AI / LLM:** Google Gemini API (Flash-Lite)
* **Databases:** PostgreSQL, Redis (Queues & Cache)
* **Infrastructure:** Docker Compose, Nginx, Linux (WSL)


## 🚀 Local Setup & Installation

**1. Clone the repository and install dependencies**
```bash
git clone [https://github.com/RuslanBaatyrbekov/job-tracker.git](https://github.com/RuslanBaatyrbekov/job-tracker.git)
cd job-tracker
composer install

```

**2. Configure Environment**
Copy the `.env.example` file and configure your database, IMAP settings, and Gemini API key:

```bash
cp .env.example .env
php artisan key:generate

```

**3. Run Infrastructure (Docker)**
Start the isolated Docker environment (Web server, PostgreSQL, Redis, Worker, and Scheduler):

```bash
docker-compose up -d --build
docker-compose exec app php artisan migrate --seed

```

**4. Initialize AI Strategy**
Add your resumes via the Filament admin panel (`/admin/job-categories`), then run the global AI analysis:

```bash
docker-compose exec app php artisan ai:strategy

```

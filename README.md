<div align="center">
  <img width="1024" height="572" alt="image" src="https://github.com/user-attachments/assets/38bdce72-ef37-4caa-89f8-2c9f35151793" />


  <h1>🚀 AI-Powered Job Application Tracker</h1>
  <p><b>An advanced, automated job-tracking system built with Laravel 11, Filament v3, and Docker. Features IMAP email parsing and Gemini AI career intelligence.</b></p>

  <p>
    <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
    <img src="https://img.shields.io/badge/Filament-v3-F59E0B?style=for-the-badge&logo=filament&logoColor=white" alt="Filament">
    <img src="https://img.shields.io/badge/Docker-24.0+-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
    <img src="https://img.shields.io/badge/Gemini-AI-8E75B2?style=for-the-badge&logo=google&logoColor=white" alt="Gemini AI">
  </p>
</div>

---

## 🌟 Overview

Managing job applications manually is tedious. **Job Tracker** automates the entire lifecycle—from parsing incoming recruiter emails via IMAP, analyzing rejection reasons or interview invites using **Google Gemini AI**, to instantly pushing actionable advice straight to your **Telegram** and displaying performance metrics on a sleek **Filament v3** dashboard.

---

## ✨ Key Features

- **🤖 AI-Driven Email Parsing & Insights:** Automatically processes incoming emails, determines application statuses (`applied`, `interview`, `rejected`, `offer`), extracts rejection reasons, and generates contextual advice based on a global knowledge base and resume strategy.
- **📱 Real-time Telegram Alerts:** Instant notifications with AI-generated verdicts and action steps delivered the moment a recruiter responds.
- **📊 Advanced Analytics Dashboard:** Interactive charts (conversion rates by category, application activity trends, status distribution) with dynamic global category filtering.
- **🐳 Fully Containerized:** Built with Docker, utilizing PostgreSQL, Redis, and a background queue worker for seamless asynchronous processing.
- **⏰ Smart Scheduler:** Automated daily syncing and strategy generation configured in the `Europe/Berlin` timezone.

---

## 🛠️ Tech Stack

- **Backend:** Laravel 11, PHP 8.3
- **Admin Panel:** Filament v3 (Tailwind CSS, Livewire)
- **Database & Cache:** PostgreSQL, Redis
- **AI & Integrations:** Google Gemini API, Telegram Bot API
- **Infrastructure:** Docker, Docker Compose, WSL

---

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

---

## 📸 Screenshots

<img width="1024" height="572" alt="image" src="https://github.com/user-attachments/assets/784bd45b-a0a4-48c6-973a-6beede5689cc" />
<img width="1024" height="765" alt="image" src="https://github.com/user-attachments/assets/2683af18-6f78-4ae3-967f-029c3265b392" />




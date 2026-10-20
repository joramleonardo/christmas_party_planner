# Christmas Party Planner

Christmas Party Planner is a full-featured web application designed to organize and manage holiday parties, gift exchanges, awards, and event logistics. Built with Laravel, Inertia.js, and Vue 3, it provides groups with tools to coordinate Secret Santa draws, manage participant wishlists, vote on fun party awards, and track food budgets and payments.

## Features

- Multi-Event and Group Support
  - Create distinct party projects using unique URL slugs.
  - Custom participant roster management per project.
  - Join flow for participants.

- Secret Santa and Gift Exchange
  - Automated Secret Santa assignments with collision prevention.
  - Codename assignment and anonymity features to keep pairings secret.
  - Unique secret codes for secure participant verification.
  - Automated email dispatch of assignments to participants.
  - Gift purchase status tracking.

- Wishlist Management
  - Code-based participant lookup to protect privacy without requiring full accounts.
  - Personalized wishlist items and detailed item descriptions.
  - Real-time updates so Secret Santas know what to buy.

- Gift Ideas and Theme Polling
  - Curated gift categories such as Small Gifts and themed suggestions.
  - Ability to save and broadcast gift ideas to attendees via email.

- Party Awards and Live Voting
  - Configurable award categories for team or party members.
  - Code-authenticated voting to prevent duplicate or invalid ballots.
  - Real-time vote status and results calculation.

- Food Planning and Expense Splitting
  - Collaborative dish and grocery item checklist with quantity and pricing.
  - Automatic calculation of total costs and per-person contributions.
  - Payer assignment and contribution tracking.
  - Payment status monitoring (unpaid/paid).
  - GCash payment account information and QR code display for quick reimbursement.

## Tech Stack

- Backend Framework: Laravel 12 (PHP 8.2+)
- Frontend Framework: Vue 3 (Composition API)
- Monolith Bridge: Inertia.js (Inertia Laravel 2.0 / Inertia Vue3)
- Styling: Tailwind CSS
- Asset Bundling: Vite
- Additional Libraries: Chart.js, vue-chartjs, canvas-confetti, vue-toastification

## Prerequisites

Before running the application locally, ensure you have the following installed:

- PHP >= 8.2
- Composer
- Node.js >= 18.x and npm
- A relational database (MySQL, PostgreSQL, or SQLite)

## Installation and Setup

1. Clone the repository:
```bash
git clone https://github.com/joramleonardo/christmas_party_planner.git
cd christmas_party_planner
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies:
```bash
npm install
```

4. Configure environment settings:
```bash
cp .env.example .env
```
Update your `.env` file with your database connection details and mail credentials.

5. Generate application encryption key:
```bash
php artisan key:generate
```

6. Run database migrations:
```bash
php artisan migrate
```

7. Build frontend assets:
For local development with hot reload:
```bash
npm run dev
```
For production build:
```bash
npm run build
```

8. Start the local server:
```bash
php artisan serve
```

Access the application in your browser at `http://127.0.0.1:8000`.

## Mail Configuration

The application sends Secret Santa assignments, access codes, and event updates via email. For notifications to function properly, configure your mail provider settings in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

For local testing, tools such as Mailpit, Mailtrap, or the `log` driver (`MAIL_MAILER=log`) can be used.

## License

This project is open-sourced software licensed under the MIT License.

# LKS Web Application

**Mari Berkarya** is a web-based application developed to support an online Student Skills Competition (Lomba Kompetensi Siswa) for SMA/SMK level organized by Kota Emas. The platform allows participants to upload their works from various fields, while judges and the general public can access and review these submissions through a web interface.

This repository contains the web application side of the system, focusing on application logic, user interaction, and integration with scalable infrastructure components.

## Features

- 🔐 User authentication (register/login) with JWT tokens
- 🖼️ Karya management with S3 storage
- 🎨 Modern UI with Franken UI components
- 🔒 Secure password hashing with bcrypt

## Requirements

### PHP
- **PHP 8.0** or higher ( tested on PHP 8.3 )

### Required PHP Extensions
- `mysql`
- `pdo`
- `mbstring`
- `xml`

### Web Server
- **Apache2** with `mod_rewrite` enabled

### Database
- **MySQL 5.7+** or **MariaDB 10.3+**

### Storage
- AWS S3 or any S3-compatible storage service (Cloudflare R2, MinIO, DigitalOcean Spaces, etc.)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd plain
```

### 2. Configure Environment Variables

Copy the sample environment file and configure your settings:

```bash
cd be
cp env.sample .env
```

Edit `.env` file with your configuration:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=lks_db

# Debug Mode
DEBUG_MODE=false

# AWS S3 Configuration
AWS_ACCESS_KEY_ID=your_aws_access_key_id
AWS_SECRET_ACCESS_KEY=your_aws_secret_access_key
AWS_REGION=ap-southeast-1
AWS_S3_BUCKET=your_bucket_name

# S3 Endpoint (optional - leave empty for AWS S3)
# For Cloudflare R2, MinIO, or other S3-compatible services
S3_ENDPOINT=
```

## Project Structure

```
plain/
├── be/                          # Backend API
│   ├── api/                     # API endpoints
│   │   ├── auth.php            # Authentication endpoints
│   │   └── galleries.php       # Gallery API endpoints
│   ├── classes/                 # Core classes
│   │   ├── Auth.php            # Authentication handler
│   │   ├── Database.php        # Database connection
│   │   ├── Response.php        # API response formatter
│   │   ├── Router.php          # Request router
│   │   └── S3Service.php       # S3 storage service
│   ├── lib/                     # Third-party libraries
│   │   └── aws/                # AWS SDK (local)
│   ├── .htaccess               # Apache rewrite rules
│   ├── config.php              # Configuration loader
│   ├── database.sql            # Database schema
│   ├── galleries.php           # Gallery management UI
│   ├── index.php               # API entry point
│   ├── login.php               # Login page
│   └── register.php            # Registration page
├── fe/                          # Frontend (if applicable)
├── assets/                      # Static assets
├── index.html                   # Landing page
└── README.md                    # This file
```

## API Endpoints

### Authentication

- `POST /be/auth/register` - Register new user
- `POST /be/auth/login` - User login

### Galleries

- `GET /be/galleries` - Get all gallery items (public)
- Gallery management UI available at `/be/galleries.php` (requires login)

## Usage

### Access the Application

1. **Landing Page:** `http://your-domain.com/`
2. **Register:** `http://your-domain.com/be/register.php`
3. **Login:** `http://your-domain.com/be/login.php`
4. **Gallery Management:** `http://your-domain.com/be/galleries.php` (after login)

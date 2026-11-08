#LECTURA

Web Technologies Pre-mids project by: Muhammad Ibraheem (BCSF24A023) and Muhammad Ali (BCSF24A024).

Lectura is a university schedule management system which allows students of all sections to view their daily, weekly, and permanent timetables. It also allows teachers to view their schedules, change class timings or venues, or request slots from other teachers. The schedules are then automatically updated for students, and the hassle of searching for free rooms and slots and class availability is eliminitated.

# Lectura on Ubuntu (LAMP: Apache + MySQL + PHP)

Tested on Ubuntu 24.04 (Apache 2.4.58, MySQL 8.0, PHP 8.3). Requires PHP 8.1 or newer,
so Ubuntu 22.04 or newer. Ubuntu 20.04 (PHP 7.4) will not work.

## Layout

```
/var/www/LECTURA/
├── public/            <- the ONLY folder Apache serves (DocumentRoot)
│   ├── index.php, logout.php
│   ├── admin/  teacher/  student/
│   └── assets/{css,js,img}
├── includes/          PHP libraries (never web-accessible)
├── config/            config.php (DB password lives here)
├── database/          schema.sql, seed.php
└── deploy/            lectura.conf (Apache virtual host)
```

## 1. Install packages

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring unzip
```

## 2. Put the project in place

```bash
sudo mkdir -p /var/www
cd /var/www
git clone https://github.com/mibraheem8009/LECTURA
```

## 3. Create the database and user

```bash
openssl rand -hex 16        # generate a password and copy it
sudo mysql
```
```sql
CREATE DATABASE lectura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lectura'@'localhost' IDENTIFIED BY 'PASTE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON lectura.* TO 'lectura'@'localhost';
EXIT;
```

Load the tables (WARNING: schema.sql drops and recreates every table. Never re-run it on live data):

```bash
mysql -u lectura -p lectura < /var/www/LECTURA/database/schema.sql
```

## 4. Configure

```bash
cd /var/www/LECTURA
sudo cp config/config.example.php config/config.php
sudo nano config/config.php          # set 'pass' to the password from step 3
```

## 5. Permissions

```bash
sudo chown -R root:www-data /var/www/LECTURA
sudo find /var/www/LECTURA -type d -exec chmod 750 {} \;
sudo find /var/www/LECTURA -type f -exec chmod 640 {} \;
```

## 6. Load the sample data (once)

```bash
cd /var/www/LECTURA
sudo -u www-data php database/seed.php
```

Default logins (change them straight after first login):

| Role    | Email                     | Password    |
|---------|---------------------------|-------------|
| Admin   | bcsf24a023@pucit.edu.pk   | admin@23    |
| Teacher | mateen@pucit.edu.pk (any teacher) | Teacher@123 |

Students need no account (Dashboard button on the login page).

## 7. Apache

```bash
sudo cp /var/www/LECTURA/deploy/lectura.conf /etc/apache2/sites-available/lectura.conf
sudo nano /etc/apache2/sites-available/lectura.conf   # set ServerName
sudo a2dissite 000-default
sudo a2ensite lectura
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Open `http://SERVER_IP/` (or your ServerName). For a local test name, add
`127.0.0.1 lectura.local` to `/etc/hosts`.

## Troubleshooting

- Blank page / HTTP 500: `sudo tail -f /var/log/apache2/lectura_error.log` (PHP errors go here; display_errors is off).
- `SQLSTATE[HY000] [1045] Access denied`: wrong password in `config/config.php`.
- `[2002] ... Permission denied` or `No such file`: set `'host' => '127.0.0.1'` in `config/config.php`.
- `Could not reliably determine the server's fully qualified domain name`: harmless; silence with
  `echo "ServerName localhost" | sudo tee /etc/apache2/conf-available/servername.conf && sudo a2enconf servername`.
- Reset the demo data: re-run steps 3 (schema import) and 6 (seed). This erases everything.

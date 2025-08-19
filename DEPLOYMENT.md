# Photo Gallery V-Server Deployment Guide

## Prerequisites
- A V-Server (VPS) running Ubuntu/Debian
- Basic terminal access to your server
- A domain name (optional but recommended)

## Step 1: Server Setup

### Connect to your V-Server
```bash
ssh root@your-server-ip
```

### Install Required Software
```bash
# Update system
apt update && apt upgrade -y

# Install Nginx web server and PHP
apt install nginx php8.1 php8.1-fpm php8.1-json php8.1-session -y

# Install SSL certificate tool (optional)
apt install certbot python3-certbot-nginx -y

# Start and enable services
systemctl start nginx
systemctl enable nginx
systemctl start php8.1-fpm
systemctl enable php8.1-fpm
```

## Step 2: Upload Your Files

### Option A: Using SCP (from your local machine)
```bash
# Upload files to server
scp -r /Users/juliusschade/Desktop/Dev/WebappFotos root@your-server-ip:/var/www/
```

### Option B: Using Git (if you have a repository)
```bash
# On server
cd /var/www/
git clone your-repository-url WebappFotos
```

### Option C: Manual Upload via FTP
Use FileZilla or similar FTP client to upload files to `/var/www/WebappFotos/`

## Step 3: Configure Nginx

### Create Nginx Configuration
```bash
# Create site configuration
nano /etc/nginx/sites-available/photo-gallery
```

Add this configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;  # Replace with your domain
    root /var/www/WebappFotos;
    index index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # PHP configuration
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # API routes
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # Main location
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Prevent access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Prevent access to data files
    location ~ \.(json|log)$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|mp4|webm)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Set max upload size
    client_max_body_size 100M;
}
```

### Enable the Site
```bash
# Enable site
ln -s /etc/nginx/sites-available/photo-gallery /etc/nginx/sites-enabled/

# Remove default site
rm /etc/nginx/sites-enabled/default

# Test configuration
nginx -t

# Restart Nginx
systemctl restart nginx
```

## Step 4: Set Permissions
```bash
# Set correct ownership
chown -R www-data:www-data /var/www/WebappFotos

# Set correct permissions for files and directories
find /var/www/WebappFotos -type f -exec chmod 644 {} \;
find /var/www/WebappFotos -type d -exec chmod 755 {} \;

# Create uploads directory with write permissions
mkdir -p /var/www/WebappFotos/api/uploads
mkdir -p /var/www/WebappFotos/api/data
chown -R www-data:www-data /var/www/WebappFotos/api/uploads
chown -R www-data:www-data /var/www/WebappFotos/api/data
chmod -R 775 /var/www/WebappFotos/api/uploads
chmod -R 775 /var/www/WebappFotos/api/data
```

## Step 5: SSL Certificate (Recommended)

### If you have a domain name:
```bash
# Get SSL certificate
certbot --nginx -d your-domain.com -d www.your-domain.com
```

### If using IP address only:
You can skip SSL for now, but it's recommended to get a domain name for security.

## Step 6: Firewall Configuration
```bash
# Allow HTTP and HTTPS
ufw allow 'Nginx Full'
ufw allow ssh
ufw enable
```

## Step 7: Access Your Application

Open your browser and navigate to:
- `http://your-domain.com` (or `http://your-server-ip`)
- Login with: Username: `admin`, Password: `admin123`

## Important Security Notes

### Change Default Admin Password
1. Login to admin panel
2. Create a new admin user with a strong password
3. Delete the default admin user

### Backup Strategy
```bash
# Create backup script
nano /home/backup-gallery.sh
```

Add this script:
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
# Backup both application and uploaded files
tar -czf /home/backups/photo-gallery-$DATE.tar.gz \
  --exclude='/var/www/WebappFotos/api/uploads/*.tmp' \
  /var/www/WebappFotos
# Keep backups for 7 days
find /home/backups -name "photo-gallery-*.tar.gz" -mtime +7 -delete
```

```bash
# Make executable and create backup directory
chmod +x /home/backup-gallery.sh
mkdir -p /home/backups

# Add to crontab for daily backups
crontab -e
# Add line: 0 2 * * * /home/backup-gallery.sh
```

## Maintenance

### Update Files
When you make changes to your app:
```bash
# Upload new files
scp -r /path/to/updated/files root@your-server-ip:/var/www/WebappFotos/

# Set permissions
chown -R www-data:www-data /var/www/WebappFotos
```

### Monitor Logs
```bash
# View Nginx access logs
tail -f /var/log/nginx/access.log

# View Nginx error logs
tail -f /var/log/nginx/error.log
```

### Server Resources
- **RAM**: Minimum 1GB recommended
- **Storage**: Depends on number of photos/videos you'll store
- **Bandwidth**: Consider your users' download needs

## Troubleshooting

### If website doesn't load:
1. Check if Nginx is running: `systemctl status nginx`
2. Check configuration: `nginx -t`
3. Check firewall: `ufw status`
4. Check logs: `tail /var/log/nginx/error.log`

### If uploads don't work:
1. Check file permissions: `ls -la /var/www/WebappFotos/api`
2. Check PHP upload settings: `php -i | grep upload`
3. Check Nginx error log: `tail /var/log/nginx/error.log`
4. Check PHP-FPM log: `tail /var/log/php8.1-fpm.log`

### If API requests fail:
1. Check Nginx configuration: `nginx -t`
2. Verify PHP-FPM is running: `systemctl status php8.1-fpm`
3. Check file permissions on data directory
4. Look for PHP errors in logs

### Performance Optimization:
- Enable Gzip compression in Nginx
- Use image optimization tools before upload
- Consider CDN for large media files
- Regular cleanup of old uploaded files

## File Storage Details

**Data Storage:**
- User accounts: `/var/www/WebappFotos/api/data/users.json`
- Libraries: `/var/www/WebappFotos/api/data/libraries.json`
- Media metadata: `/var/www/WebappFotos/api/data/media.json`
- Uploaded files: `/var/www/WebappFotos/api/uploads/`

**File Limits:**
- Max file size: 100MB (configurable in PHP and Nginx)
- Supported formats: JPG, PNG, GIF, WebP, MP4, WebM, OGG
- Unlimited storage (depends on server disk space)

Your photo gallery is now ready to use with server-side file storage! Remember to regularly backup your data and keep your server updated.
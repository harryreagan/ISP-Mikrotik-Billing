import subprocess

nginx_conf = """server {
    listen 80;
    server_name isp.hsmart.co.ke billing.hsmart.co.ke;

    # Allow MikroTik RouterOS provisioning & heartbeat directly over HTTP
    location ^~ /provision/ {
        root /var/www/poss/isp-billing/public;
        try_files $uri /index.php?$query_string;

        location ~ \\.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/poss/isp-billing/public/index.php;
            fastcgi_param DOCUMENT_ROOT /var/www/poss/isp-billing/public;
            fastcgi_read_timeout 300s;
        }
    }

    location ^~ /api/router-hello/ {
        root /var/www/poss/isp-billing/public;
        try_files $uri /index.php?$query_string;

        location ~ \\.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/poss/isp-billing/public/index.php;
            fastcgi_param DOCUMENT_ROOT /var/www/poss/isp-billing/public;
            fastcgi_read_timeout 300s;
        }
    }

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    client_max_body_size 64M;
    server_name isp.hsmart.co.ke billing.hsmart.co.ke;

    ssl_certificate     /etc/letsencrypt/live/pos.hsmart.co.ke/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pos.hsmart.co.ke/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    root /var/www/poss/isp-billing/public;
    index index.php index.html;

    location ~* ^/build/assets/.+\\.(js|css|woff2?|ttf|svg|png|jpg|webp|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable, max-age=31536000";
        try_files $uri =404;
    }

    location ^~ /storage/ {
        alias /var/www/poss/isp-billing/storage/app/public/;
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
    }

    location ~ \\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /var/www/poss/isp-billing/public$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT /var/www/poss/isp-billing/public;
        fastcgi_param HTTPS on;
        fastcgi_read_timeout 300s;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ /\\. {
        deny all;
    }
}
"""

with open("c:\\xampp\\htdocs\\poss\\isp-billing\\temp_nginx.conf", "w", encoding="utf-8") as f:
    f.write(nginx_conf)

subprocess.run(["scp", "-o", "StrictHostKeyChecking=no", "c:\\xampp\\htdocs\\poss\\isp-billing\\temp_nginx.conf", "root@213.199.49.216:/etc/nginx/sites-available/isp.hsmart.co.ke.conf"])
res = subprocess.run(["ssh", "-o", "StrictHostKeyChecking=no", "root@213.199.49.216", "nginx -t && systemctl reload nginx"])
print("Nginx reload returncode:", res.returncode)

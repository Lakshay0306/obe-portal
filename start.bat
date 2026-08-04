@echo off
echo Starting PHP Development Server...
echo Make sure you keep this window open while using the app!
start http://localhost:8000/login.html
php -S localhost:8000 -t public

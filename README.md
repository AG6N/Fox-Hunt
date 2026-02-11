I am not a programmer.  This code was 100% slopped together with AI.  I'm sure you can tell by the readme below, and by reviewing the actual code.  While I didn't do any actual, real work on this, I did learn some mechanics of PHP and SQL, so that's a win for me.

The application will probably break in 100 different ways that I have not discovered, but it does work as a proof of concept for an idea that I had while sitting in the grass on a warm sunny day.  That idea is to gameify transmitter fox hunting.  Anyone can create an account and hide a new fox.  The fox is given a serial number which should be attached to the transmiter.  When someone else finds the fox, they take note of the serial number and enter it into the app under their account.  If the serial numbers match, the finder get points...  

If your club is hosting a fox hunt event, this will probably be good enough to assign serial numbers to transmitters, and track who found what in one place.



# Foxhunt - Radio Transmitter Hunting Game v2.1

## Description
Foxhunt is a PHP application that simulates the real-world radio sport of transmitter hunting. Users can hide "fox" transmitters and others can search for them to earn points.

## New Features in v2.1
- **Frequency field**: 8-character field for operating frequency (e.g., 146.520, 446.000)
- **Mode field**: 4-character field for transmission mode (e.g., FM, SSB, CW, AM)
- **RF Power field**: Expanded from 4 to 5 characters to support values like "1.5W", "100mW"

## Features
- Hide transmitters with 8-digit serial numbers in 6-digit grid squares
- Specify frequency and mode for each transmitter
- Search for active transmitters by frequency and mode
- Verify finds using serial numbers
- Leaderboard tracking
- Game statistics

## Installation

1. **Requirements**
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Web server (Apache, Nginx, etc.)

2. **Setup Steps**
   ```bash
   # 1. Clone or copy files to your web server directory
   # 2. Import the updated database schema:
   mysql -u username -p < database.sql
   
   # 3. Configure database connection in config.php
   # 4. Ensure the web server can write to the directory
   # 5. Access the application via browser

This needs to be run on the database so user admin can log in for the first time
UPDATE users SET password_hash = MD5('admin123') WHERE username = 'admin';

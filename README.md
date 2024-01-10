# === watchpower-voltronic-log-web-charts v1.5 ===

![Screenshot](./watchpower-voltronic-log-web-charts%20v1.4.png?raw=true "Screenshot")

=== STATUS: IT WORKS!

=== ABOUT ===

this is a PHP + HTML + JS based "program" that can read log files created by the WatchPower solar hybrid inverter monitoring software (Java based)

WatchPower only writes debug files when in the top right corner the symbol r-click -> debug mode is on.

then it should write log files like: 2023-10-30 USB-QPIGS.log

o this program will read the log files from ./data subdirectory

o in order to mount the live data mount --bind can be used

mkdir /var/www/html/data
mount --bind /home/user/software/WatchPower/log/debug/ /var/www/html/data
chown -R www-data /var/www/html

o sort them by their date-filename.log

o read them line by line and try to generate charts

o so users can (better) visualize their energy production & consumption

PS: yes really could not think of a shorter name X-D

==== PROBLEMS ====

=== non-senese-values ===

solution: in WatchPower, try to (taskbar) r-click on WatchPower-symbol -> switch off and on DEBUG mode (checkbox)

description: lately discovered... that not only does the USB connected inverter during boot send a bunch of stuff, that might be 
interpreted by the computer as "keyboard" input and hence, stop the boot process (solution: off/on... no good)

also: it started reporting nonsensical values for battery voltage (on the display it shows the correct voltage)

but in the data field[10] is supposed to be battery voltage: 00.40V (#wtf!?)

2024-01-10
2024-01-10 14:22:59 232.9 49.9 232.9 49.9 0093 0049 001 323 00.40 000 000 0006 0000 000.0 00.00 00000 00010000 00 00 00000 010

for comparison: field[10] says: 49.70V

2024-01-06
2024-01-06 00:00:05 231.5 50.0 229.9 50.0 0068 0030 001 348 49.70 000 075 0025 0000 000.0 00.00 00002 00010000 00 00 00000 010

what was changed?

not much, tried to use a galvanic usb separator, if it would help prevent the boot (sometimes)
but did not help (off/on it is... )

but the other values are meassured correctly/transmitted correctly, so it can not be the problem.


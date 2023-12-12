===== watchpower-voltronic-log-web-charts  =====

![Screenshot](./watchpower-voltronic-log-web-charts%20.png?raw=true "Screenshot")

IT WORKS BUT FAR FROM "FINISHED" :D

this is a PHP + HTML + JS based "program" that can read log files created by the WatchPower solar hybrid inverter monitoring software (Java based)

WatchPower only writes debug files when in the top right corner the symbol r-click -> debug mode is on.

then it should write log files like: 2023-10-30 USB-QPIGS.log

o this program will read the log files from ./data subdirectory

o sort them by their date-filename.log

o read them line by line and try to generate charts

o so users can (better) visualize their energy production & consumption

PS: yes really could not think of a shorter name X-D

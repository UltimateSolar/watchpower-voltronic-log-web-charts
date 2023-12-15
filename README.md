# watchpower-voltronic-log-web-charts v1.4

[![screenshot]([https://github.com/altercation/solarized/raw/master/img/solarized-yinyang.png](https://raw.githubusercontent.com/UltimateSolar/watchpower-voltronic-log-web-charts/main/watchpower-voltronic-log-web-charts%20v1.4.png))](#screenshot)

IT WORKS BUT FAR FROM FINISHED :D

this is a PHP + HTML + JS based "program" that can read log files created by the WatchPower solar hybrid inverter monitoring software (Java based)

WatchPower only writes debug files when in the top right corner the symbol r-click -> debug mode is on.

then it should write log files like: 2023-10-30 USB-QPIGS.log

o this program will read the log files from ./data subdirectory

o sort them by their date-filename.log

o read them line by line and try to generate charts

o so users can (better) visualize their energy production & consumption

PS: yes really could not think of a shorter name X-D

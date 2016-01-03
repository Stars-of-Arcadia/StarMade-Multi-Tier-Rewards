# StarMade-Multi-Tier-Rewards

## ChangeLog:

### 0.0.3
 - Removed all local time tracking since starmade-servers.com
   API uses a 24 hr EST/EDT day, as opposed to a rolling 24hr (86400s) period.
 - Removed local timezone to config file

### 0.0.2
 - Fix timezone delta on starmade-servers.com API timestamp
 - Add local timezone to config file
 - Fix reset for missed vote days, reported by @CaptianJack

### 0.0.1
 - Initial Release

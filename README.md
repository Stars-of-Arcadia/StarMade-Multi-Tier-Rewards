# StarMade-Multi-Tier-Rewards
Multi-tiered configurable rewards script for StarMade servers.  
Based on rewardvotes.php by: Mike Sheen (mike@sheen.id.au)  

## Highlights:
 - Increasing rewards for voting on consecutive days.
 - Multipliers/benefits for consecutive voting are lost if a day is missed.
 - Configurable reward tiers for a high degree of customization.
 - Creates vote-reward urgency.

## Current Configurable Rewards:
 - Credits
 - Faction Points
 - Blocks

## Planned Configurable Rewards:
 - Commands
 - Entities
 - ??

## Setup

### Main Config File
Edit the config file (multi-tier-rewards.cfg.example) to your required settings,  
and rename it to multi-tier-rewards.cfg.  

    ; this is the Super admin password from the StarMade server.cfg
    adminpassword = "YOUR_SUPER_ADMIN_PASS"

    ; This is the API Key issued to your listing by starmade-servers.com
    serverkey = "YOUR_API_KEY_FROM_STARMADE-SERVERS.COM"

    ; Full path to the StarNet jar file provided with StarMade
    starnetpath = "/home/steam/starmade/tools"

    ; if you plan on calling this from a crontab, you must fully path all file locations
    javapath = "/usr/bin"

### Rewards Config Map File
Edit the rewards config (.ini) to your desired settings.  

    ; If you make new tiers,
    ; make sure to use sections
    [0]
    ; Repeat Days How many days on this Tier?
    repeat = 5

    ; Multiplier per Repeat
    multiplier = 2

    ; Inherit the previous tiers exported rewards
    inherit = false

    ; Allow other tiers to inherit this tiers rewards
    export = true

    ; Rewards per Repeat
    rewards[credits] = 500000

    [1]
    repeat = 5
    multiplier = 2
    inherit = true
    export = true
    rewards[faction_points] = 1

    [2]
    repeat = 5
    multiplier = 2
    inherit = true
    export = true
    ; Block format is comma delimited, block_id:quantity,[block_id:quantity,...]
    rewards[blocks] = 343:5,1:1

### Permissions & Cron
Make sure to chown and/or chmod the main script to an appropriate user on your system,  
`chown someone:someone multi-tier-rewards.php`  
`chmod +x multi-tier-rewards.php`  

Cron it for however frequent you like, I chose every 5 mins.  
`*/5 * * * * /usr/bin/php /path/to/multi-tier-rewards/multi-tier-rewards.php 2>&1 >> /path/to/multi-tier-rewards/multi-tier-rewards.log`

<?php

/////////////////////////////////
// Title: multi-tier-rewards.php
// Author: Chris Hart (chart@linux.com)
// a.k.a. Archpoet (archipoetae@gmail.com)
// Date: 2015.12.12-15
/////////////////////////////////

// Based on rewardvotes.php
// by: Mike Sheen (mike@sheen.id.au)
// Date: 18 July 2014

//
// CONFIGURE DATA STRUCTS
//

// Config file for this script
$config_file = dirname( __FILE__ ) . "/multi-tier-rewards.cfg";

// Rewards Map file for this script
$ini_file = dirname( __FILE__ ) . "/multi-tier-rewards.ini";

// Existing rewards data
$dat_file = dirname( __FILE__ ) . "/multi_tier_rewards_map.dat";

// Pseudo-Globals / Storage
$config = array();
$user_data = array();
$user_new_data = array();
$reward_map = array();

// DEFAULT Rewards Tier Map -- Use Config INI instead if desired
$default_reward_map = array(
	// TIER 0
	array(
		'tier' => 0,

		// Repeat Days, How many days on this Tier?
		'repeat' => 5,

		// Multiplier Per Repeat
		'multiplier' => 2,

		// Inherit the previous tiers' exported rewards
		'inherit' => false,

		// Allow other tiers to inherit this tier's rewards
		'export' => true,

		// the rewards
		'rewards' => array(
			'credits' => 500000,
		),
	),
	// TIER 1
	array(
		'tier' => 1,
		'repeat' => 5,
		'multiplier' => 2,
		'inherit' => true,
		'export' => true,
		'rewards' => array(
			'faction_points' => 1,
		),
	),
	// TIER 2
	array(
		'tier' => 2,
		'repeat' => 5,
		'multiplier' => 2,
		'inherit' => true,
		'export' => true,
		'rewards' => array(
			'blocks' => '343:5,1:1', // Gold Bars, ShipCore
		),
	),
);

//
// LOAD EXISTING CONFIG/DATA
//

// Config is required
if ( file_exists($config_file) ) {
	$config = parse_ini_file($config_file);
} else {
	echo "ERROR: A config file named $config_file is required\n";
	exit(1);
}
// Bail here if the config is bad
if (count($config) == 0) {
	echo "ERROR: There was an issue parsing the config file: $config_file\n";
	exit(1);
}

// Read these in if they exist
if ( file_exists($dat_file) ) {
	$user_data = parse_ini_file($dat_file, true);
}

if ( file_exists($ini_file) ) {
	$reward_map = parse_ini_file($ini_file, true);
} else {
	$reward_map = $default_reward_map;
}

//
// MAIN
//

// Starmade-servers.com provides EST timestamps,
date_default_timezone_set('America/New_York');
$now = time();

// Get All Votes, (last 50)
$url = "http://starmade-servers.com/api/?object=servers&element=votes&key=" . $config['serverkey'];

$options = array(
	'http' => array(
		'header'  => "Content-type: application/json\r\n",
		'method'  => 'GET',
	),
);

$context = stream_context_create($options);
$contents = file_get_contents($url, false, $context);
$result = json_decode($contents, true);
$voters = array();

// If we got back votes
if (sizeof($result['votes']) > 0) {

	// Iterate and process them all
	foreach ($result['votes'] as $vote) {

		 // Tho, we're only interested in votes not claimed
		if ($vote['claimed'] == 0) {

			// in the last 24hrs
			if (($now - $vote['timestamp']) <= 86400) {
				// handle steamid also /* later */
				array_push( $voters,
					array(
						'name' => $vote['nickname'],
						'steam' => $vote['steamid'],
						'timestamp' => $vote['timestamp'],
						'claimed' => $vote['claimed']
					)
				);
			}

		}
	}

	// Bail now if we have no valid voters,
	// or go ahead and Start-er-up.
	if ( count($voters) > 0 ) {
		echo("==== MULTI-TIER VOTE REWARDS -- START: " . date('l jS \of F Y h:i:s A') . " ====\n");
	} else {
		exit(1);
	}

	foreach ($voters as $data) {

		// first timer, create a new entry
		if (! isset($user_data[$data['name']])) {
			$user_new_data[$data['name']] = array('consecutive_votes' => 1);
		}

		// Technically: We need to do this, because we can't set a vote as claimed unless it was within the last 24 hours 
		// per starmade-servers.com says rules.
		$url = "http://starmade-servers.com/api/?object=votes&element=claim&key=";
		$url .= $config['serverkey'] . "&username=" . $data['name'];

		$options = array(
			'http' => array(
				'header'  => "Content-type: application/json\r\n",
				'method'  => 'GET',
			),
		);

		$context = stream_context_create($options);
		$contents = file_get_contents($url, false, $context);

		// Return value of "0" means not voted in the last day -
		// "2" voted in last day, already claimed
		if ($contents == "0") {
			// user missed a day
			// set back to 1 vote so they can still redeem a vote today. @captianjack
			$user_new_data[$data['name']]['consecutive_votes'] = 1;

		// Return value of "1" means they've voted in the last day, not yet claimed
		} else if ($contents == "1") {

			echo("Unclaimed vote found for " . $data['name'] . " " . $data['steam'] . "\n");

			// Test to confirm player is online
			if ( player_is_online($data['name']) ) {

				// Try to claim this vote, otherwise don't give out rewards
				$url = "http://starmade-servers.com/api/?action=post&object=votes&element=claim&key=";
				$url .= $config['serverkey'] . "&username=" . $data['name'];

				$options = array(
					'http' => array(
						'header'  => "Content-type: application/json\r\n",
						'method'  => 'POST'
					),
				);

				$context = stream_context_create($options);
				$result = file_get_contents($url, false, $context);

				// page will return 1 if everything was ok, otherwise 0
				if ($result == "1") {
					// all good
					// increment consecutive votes
					$user_new_data[$data['name']]['consecutive_votes'] = $user_data[$data['name']]['consecutive_votes'] + 1;

					// decide the user's rewards for today based on the mapping
					$votes = $user_new_data[$data['name']]['consecutive_votes'];
					$real_tier = 0;
					$tier_delta = 0;
					$repeat_count = 0;
					for ($i=0; $i < count($reward_map); $i++) {
						$repeat_count += $reward_map[$i]['repeat'];
						$tier_delta = $votes - $repeat_count;
						if ( $tier_delta < 0 ) {
							$real_tier = $i;
							break;
						}
					}

					$reward_objects = array();

					// if the current tier inherits
					if ( $reward_map[$real_tier]['inherit'] == true ) {
						// inherit all previous exported rewards.
						for ($j = 0; $j < $real_tier; $j++) {
							if ($reward_map[$j]['export'] == true ) {
								$reward_objects[] = $reward_map[$j];
							}
						}
					}

					// actual tier goes on last
					$reward_objects[] = $reward_map[$real_tier];

					$reward_credits = 0;
					$reward_faction_points = 0;
					$reward_block_count = 0;
					$reward_blocks = array();
					$reward_commands = array();
					$reward_entities = array();

					// now that we have all the reward objects for this user
					// iterate and process them
					$repeat_count = 0;
					for ($i=0; $i < count($reward_objects); $i++) {
						$multi = $reward_objects[$i]['multiplier'];
						$repeat_count += $reward_objects[$i]['repeat'];
						$tier_increment = $votes - $repeat_count;

						if ( $tier_increment < 0 ) {
							$tier_increment = $reward_objects[$i]['repeat'] - abs($tier_increment);
						} else {
							$tier_increment = $reward_objects[$i]['repeat'];
						}

						if ( isset($reward_objects[$i]['rewards']) && count($reward_objects[$i]['rewards']) > 0 ) {
							$this_tier_rewards = $reward_objects[$i]['rewards'];
							foreach ($this_tier_rewards as $reward => $value) {
								switch($reward) {
									case 'credits':
										$reward_credits += ($tier_increment * $multi) * $value;
									break;
									case 'faction_points':
										$reward_faction_points += ($tier_increment * $multi) * $value;
									break;
									case 'blocks':
										$blocks = preg_split('/,\s?/', $value);
										foreach ($blocks as $block_string) {
											list($block_id,$count) = preg_split('/:\s?/', $block_string);
											$amount = ($tier_increment * $multi) * $count;
											if (isset($reward_blocks[$block_id])) {
												$reward_blocks[$block_id] += $anount;
											} else {
												$reward_blocks[$block_id] = $amount;
											}
											$reward_block_count += $amount;
										}
									break;
								}
							}
						}

						// same calc as above, reiterated through the previous reward tiers
						for ($j=0; $j < $i; $j++) {
							$mul = $reward_objects[$j]['multiplier'];

							if ( isset($reward_objects[$j]['rewards']) && count($reward_objects[$j]['rewards']) > 0 ) {
								$reiterated_tier_rewards = $reward_objects[$j]['rewards'];
								foreach ($reiterated_tier_rewards as $reward => $value) {
									switch($reward) {
										case 'credits':
											$reward_credits += ($tier_increment * $mul) * $value;
										break;
										case 'faction_points':
											$reward_faction_points += ($tier_increment * $mul) * $value;
										break;
										case 'blocks':
											$blocks = preg_split('/,\s?/', $value);
											foreach ($blocks as $block_string) {
												list($block_id,$count) = preg_split('/:\s?/', $block_string);
												$amount = ($tier_increment * $mul) * $count;
												if (isset($reward_blocks[$block_id])) {
													$reward_blocks[$block_id] += $anount;
												} else {
													$reward_blocks[$block_id] = $amount;
												}
												$reward_block_count += $amount;
											}
										break;
									}
								}
							}
						}

					}

					$rewards_string = '';

					// process all the rewards
					$claimed = false;
					if ($reward_credits > 0) {
						give_credits($data['name'], $reward_credits);
						$rewards_string .= $reward_credits . ' credits';

						$message = '[HERALD] Gave you ' . $reward_credits . ' Credits.';
						send_pm($data['name'], $message);
						$claimed = true;
					}
					if ($reward_faction_points > 0) {
						give_faction_points($data['name'], $reward_faction_points);
						$rewards_string .= ' ' . $reward_faction_points . ' FP';

						$message = '[HERALD] Gave you ' . $reward_faction_points . ' Faction Points';
						send_pm($data['name'], $message);
						$claimed = true;
					}
					if (count($reward_blocks) > 0) {
						give_blocks($data['name'], $reward_blocks);
						$rewards_string .= ' ' . $reward_block_count . ' blocks';

						$message = '[HERALD] Gave you ' . $reward_block_count . ' Blocks';
						send_pm($data['name'], $message);
						$claimed = true;
					}

					// Were they able to claim a reward of some value?
					if ($claimed == true) {

						$message = 'Vote again tomorrow for even better rewards!';
						send_pm($data['name'], $message);

						echo(" * Gave player c:$reward_credits fp:$reward_faction_points b:$reward_block_count");
						echo(" rewards ok: claiming.\n");

						echo(" * Broadcasting the transaction.\n");

						$message = '[HERALD] Gave ' . $data['name'] . ' ' . $rewards_string;
						$message .= ' for voting for us ' . $user_new_data[$data['name']]['consecutive_votes'];
						$message .= ' days in a row on starmade-servers.com';
						send_chat($message);
					} else {
						echo(" * Player had no rewards: vote NOT claimed.\n");
						unset($user_new_data[ $data['name'] ]);
					}

				} else {
					echo(" * Error claiming vote: vote NOT claimed.\n");
					unset($user_new_data[ $data['name'] ]);
				}
			} else {
				echo(" * Player was not online: vote NOT claimed.\n");
				unset($user_new_data[ $data['name'] ]);
			}
		}
	}
}

# Re-Merge the user data back into the original,
foreach ($user_new_data as $player => $data) {
	$user_data[ $player ] = $data;
}

# and write it back to the dat file
write_ini_file($user_data, $dat_file, true);

# Fin.
echo("==== MULTI-TIER VOTE REWARDS -- END: " . date('l jS \of F Y h:i:s A') . " ====\n\n");

//
// SUPPORTING FUNCTIONS
//

// Get Player Faction ID
function get_faction_id($player) {
	list($output,$exitval) = starnet_cmd('/player_info ' . $player);

	$faction_id = 0;
	foreach ($output as $line) {
		if ( preg_match('/FACTION/', $line) ) { 
			$items = preg_split('/=/', $line);
			$tmp_array = preg_split('/=/', $items[1]);
			$faction_id = preg_replace('/,.+/','', $tmp_array[0]);
		}
	}

	return $faction_id;
}

// Give Blocks
function give_blocks($player, $blocks=array()) {
	if ( count($blocks) > 0 ) {
		foreach ($blocks as $block_id => $count) {
			list($output,$exitval) = starnet_cmd('/giveid ' . $player . ' ' . $block_id . ' ' . $count);
			if ( $exitval != 0 ) {
				return false;
			}
		}
		return true;
	}
	return false;
}

// Give Credits
function give_credits($player, $credits=0) {
	if ( $credits > 0 ) {
		list($output,$exitval) = starnet_cmd('/give_credits ' . $player . ' ' . $credits);
		if ( $exitval == 0 ) {
			return true;
		}
	}
	return false;
}

// Give Faction Points
function give_faction_points($player, $fp=0) {
	if ( $fp > 0 ) {
		$faction_id = get_faction_id($player);
		list($output,$exitval) = starnet_cmd('/faction_point_add ' . $faction_id . ' ' . $fp);
		if ( $exitval == 0 ) {
			return true;
		}
	}
	return false;
}

// Is Player Online?
function player_is_online($player) {
	$online = false;
	list($output,$exitval) = starnet_cmd('/player_info ' . $player);

	// Offline players are 'IP: null'
	foreach ($output as $line) {
		if (preg_match('/IP: \/\d{1,3}/', $line)) {
			$online = true;
		}
	}

	return $online;
}

// Global Chat
function send_chat($message) {
	if (isset($message)) {
		list($output,$exitval) = starnet_cmd('/chat ' . $message);
		if ( $exitval == 0 ) {
			return true;
		}
	}
	return false;
}

// Private Message
function send_pm($player, $message) {
	if (isset($message) && isset($player)) {
		list($output,$exitval) = starnet_cmd('/pm ' . $player . ' ' . $message);
		if ( $exitval == 0 ) {
			return true;
		}
	}
	return false;
}

// Run StarNet Command
function starnet_cmd($command) {
	global $config;

	$output = '';
	$exitval = 0;

	$_command = $config['javapath'] . '/java -jar ' . $config['starnetpath'] . '/StarNet.jar ';
	$_command .= '127.0.0.1:4242 ' .  $config['adminpassword'] . ' ';
	$_command .= $command;
	$_command .= ' 2>&1';

	exec($_command, $output, $exitval);
	return array($output, $exitval);
}


// Write an INI file
function write_ini_file($assoc_arr, $path, $has_sections=FALSE) { 
    $content = ""; 
    if ($has_sections) { 
        foreach ($assoc_arr as $key=>$elem) { 
            $content .= "[".$key."]\n"; 
            foreach ($elem as $key2=>$elem2) { 
                if(is_array($elem2)) 
                { 
                    for($i=0;$i<count($elem2);$i++) 
                    { 
                        $content .= $key2."[] = \"".$elem2[$i]."\"\n"; 
                    } 
                } 
                else if($elem2=="") $content .= $key2." = \n"; 
                else $content .= $key2." = \"".$elem2."\"\n"; 
            } 
        } 
    } 
    else { 
        foreach ($assoc_arr as $key=>$elem) { 
            if(is_array($elem)) 
            { 
                for($i=0;$i<count($elem);$i++) 
                { 
                    $content .= $key."[] = \"".$elem[$i]."\"\n"; 
                } 
            } 
            else if($elem=="") $content .= $key." = \n"; 
            else $content .= $key." = \"".$elem."\"\n"; 
        } 
    } 

    if (!$handle = fopen($path, 'w')) { 
        return false; 
    }

    $success = fwrite($handle, $content);
    fclose($handle); 

    return $success; 
}


?>

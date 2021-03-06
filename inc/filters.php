<?php
/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

class Filter {
	public $flood_check;
	private $condition;
	private $post;
	
	public function __construct(array $arr) {
		foreach ($arr as $key => $value)
			$this->$key = $value;		
	}
	
	public function match($condition, $match) {
		$condition = strtolower($condition);

		$post = &$this->post;
		
		switch($condition) {
			case 'custom':
				if (!is_callable($match))
					error('Custom condition for filter is not callable!');
				return $match($post);
			case 'flood-match':
				if (!is_array($match))
					error('Filter condition "flood-match" must be an array.');
								
				// Filter out "flood" table entries which do not match this filter.
				
				$flood_check_matched = array();
				
				foreach ($this->flood_check as $flood_post) {
					foreach ($match as $flood_match_arg) {
						switch ($flood_match_arg) {
							case 'ip':
								if ($flood_post['ip'] != $_SERVER['REMOTE_ADDR'])
									continue 3;
								break;
							case 'body':
								if ($flood_post['posthash'] != make_comment_hex($post['body_nomarkup']))
									continue 3;
								break;
							case 'file':
								if (!isset($post['filehash']))
									return false;
								if ($flood_post['filehash'] != $post['filehash'])
									continue 3;
								break;
							case 'board':
								if ($flood_post['board'] != $post['board'])
									continue 3;
								break;
							case 'isreply':
								if ($flood_post['isreply'] == $post['op'])
									continue 3;
								break;
							default:
								error('Invalid filter flood condition: ' . $flood_match_arg);
						}
					}
					$flood_check_matched[] = $flood_post;
				}
				$this->flood_check = $flood_check_matched;
				
				return !empty($this->flood_check);
			case 'flood-time':
				foreach ($this->flood_check as $flood_post) {
					if (time() - $flood_post['time'] <= $match) {
						return true;
					}
				}
				return false;
			case 'flood-count':
				$count = 0;
				foreach ($this->flood_check as $flood_post) {
					if (time() - $flood_post['time'] <= $this->condition['flood-time']) {
						++$count;
					}
				}
				return $count >= $match;
			case 'name':
				return preg_match($match, $post['name']);
			case 'trip':
				return $match === $post['trip'];
			case 'email':
				return preg_match($match, $post['email']);
			case 'subject':
				return preg_match($match, $post['subject']);
			case 'body':
				return preg_match($match, $post['body_nomarkup']);
			case 'filehash':
				return $match === $post['filehash'];
			case 'filename':
				if (!$post['files'])
					return false;

				foreach ($post['files'] as $file) {
					if (preg_match($match, $file['filename'])) {
						return true;
					}
				}
				return false;
			case 'extension':
				if (!$post['files'])
					return false;

				foreach ($post['files'] as $file) {
					if (preg_match($match, $file['extension'])) {
						return true;
					}
				}
				return false;
			case 'ip':
				return preg_match($match, $_SERVER['REMOTE_ADDR']);
			case 'op':
				return $post['op'] == $match;
			case 'has_file':
				return $post['has_file'] == $match;
			case 'board':
				return $post['board'] == $match;
			case 'password':
				return $post['password'] == $match;
			default:
				error('Unknown filter condition: ' . $condition);
		}
	}
	
	public function action() {
		global $board;
		global $config;

		$this->add_note = isset($this->add_note) ? $this->add_note : false;
		if ($this->add_note) {
			$query = prepare('INSERT INTO ``ip_notes`` VALUES (NULL, :ip, :mod, :time, :body)');
	                $query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
        	        $query->bindValue(':mod', -1);
	                $query->bindValue(':time', time());
	                $query->bindValue(':body', "Autoban message: ".$this->post['body']);
	                $query->execute() or error(db_error($query));
		}				
		if (isset ($this->action)){ 
			switch($this->action) {
				case 'reject':
					error(isset($this->message) ? $this->message : 'Posting throttled by filter.');
					break;
				case 'ban':
					if (!isset($this->reason))
						error('The ban action requires a reason.');
					
					$this->expires = isset($this->expires) ? $this->expires : false;
					$this->reject = isset($this->reject) ? $this->reject : true;
					$this->all_boards = isset($this->all_boards) ? $this->all_boards : false;
					
					Bans::new_ban($_SERVER['REMOTE_ADDR'], $this->reason, $this->expires, $this->all_boards ? false : $board['uri'], -1);

					if ($this->reject) {
						if (isset($this->message))
							error($message);
						
						checkBan($board['uri']);
						exit;
					}
					
					break;
				case 'captcha':
					$holding_id = hash('sha256', rand(0,100) . time());
					buildHoldingTable($this->post, $holding_id );
					if($config['flood_recaptcha']  && $this->post['captype'] == 'recaptcha'){
						captcha(isset($this->message) ? $this->message .
							"<hr style='width:40%'/>
							<form action='/post.php' method='post'><iframe class='recaptcha' src='https://www.google.com/recaptcha/api/fallback?k=" . $config['recaptcha_public'] . "'></iframe><br/>
							<textarea style='width: 95%;' name='recaptcha' placeholder='Captcha code goes here' required></textarea>
							<input name='reference' type='hidden' value='".  $holding_id . "'>
							<input name='release' type='hidden' value='submit'>
							<input name='board' type='hidden' value='".  $this->post['board'] . "'><br/>
							<input type='submit'>
							<br/><hr/>
							</form><hr/>" : 'Error Message Captcha.');
					}
					else if($config['flood_captchouli'] && $this->post['captype'] == 'captchouli'){
						captcha(isset($this->message) ? $this->message .
							"<hr style='width:40%'/>
							<form action='/post.php' method='post'><iframe class='captchouli' style='' src='" . $config['captchouli_addr'] . "captcha'><!-- god i hate cloudflare --></iframe><br/>
							<textarea style='width: 95%;' name='captchouli' placeholder='Captcha code goes here' required></textarea>
							<input name='reference' type='hidden' value='".  $holding_id . "'>
							<input name='release' type='hidden' value='submit'>
							<input name='board' type='hidden' value='".  $this->post['board'] . "'><br/>
							<input type='submit'>
							<br/><hr/>
							</form><hr/>" : 'Error Message Captchouli .');
					}
					else{
						error($this->post['captype'] . " isn't available right now");
					}
					//store post details in temp(on expiration timer) and await a captcha fill out to retrieve them
					break;
				default:
					error('Unknown filter action: ' . $this->action);
			}
		}
	}
	
	public function check(array $post) {
		$this->post = $post;
		foreach ($this->condition as $condition => $value) {
			if ($condition[0] == '!') {
				$NOT = true;
				$condition = substr($condition, 1);
			} else $NOT = false;
			
			if ($this->match($condition, $value) == $NOT)
				return false;
		}
		return true;
	}
}

function buildHoldingTable($post, $holding_id){
	global $config, $board;
	
	$post['reference'] = $holding_id;
	$post['num_files'] = sizeof($post['files']);
	
	Post_ImageProcessing::proccess($post);

	withhold($post);
		
	$time = time() - $config['captcha_flood_hold_time'];
	$query = prepare("SELECT files FROM ``withheld`` WHERE `time` < $time") or error(db_error());
	$query->execute();
	$to_delete = $query->fetchAll();

	foreach($to_delete as $details){
		if (!$details['files']){
		    continue;
		}
		$file = json_decode($details['files'], true)[0];
		if(isset($file['file'])) file_unlink($file['file']);
		if(isset($file['thumb'])) file_unlink($file['thumb']);
	}

	$query = prepare("DELETE FROM ``withheld`` WHERE `time` < $time") or error(db_error());
	$query->execute();
}

function purge_flood_table() {
	global $config;
	
	// Determine how long we need to keep a cache of posts for flood prevention. Unfortunately, it is not
	// aware of flood filters in other board configurations. You can solve this problem by settings the
	// config variable $config['flood_cache'] (seconds).
	
	if (isset($config['flood_cache'])) {
		$max_time = &$config['flood_cache'];
	} else {
		$max_time = 0;
		foreach ($config['filters'] as $filter) {
			if (isset($filter['condition']['flood-time']))
				$max_time = max($max_time, $filter['condition']['flood-time']);
		}
	}
	
	$time = time() - $max_time;
	
	query("DELETE FROM ``flood`` WHERE `time` < $time") or error(db_error());
}

function do_filters(array $post) {
	global $config;
	
	if (!isset($config['filters']) || empty($config['filters']))
		return;
	
	foreach ($config['filters'] as $filter) {
		if (isset($filter['condition']['flood-match'])) {
			$has_flood = true;
			break;
		}
	}
	if (isset($has_flood)) {
		//stager down specificity
		if(!$config['flood_board_active']){
			if ($post['has_file']) {
				$query = prepare("SELECT * FROM ``flood`` WHERE `ip` = :ip OR `posthash` = :posthash OR `filehash` = :filehash");
				$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
				$query->bindValue(':posthash', make_comment_hex($post['body_nomarkup']));
				$query->bindValue(':filehash', $post['filehash']);
			}
			else{
				$query = prepare("SELECT * FROM ``flood`` WHERE `ip` = :ip OR `posthash` = :posthash");
				$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
				$query->bindValue(':posthash', make_comment_hex($post['body_nomarkup']));
			}
		}
		else if($config['flood_board_active']){
			$query = prepare("SELECT * FROM ``flood`` WHERE `board` = :board");
			$query->bindValue(':board', $post['board']);
		}
		$query->execute() or error(db_error($query));
		$flood_check = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$flood_check = false;
	}
	
	foreach ($config['filters'] as $filter_array) {
		$filter = new Filter($filter_array);
		$filter->flood_check = $flood_check;
		if ($filter->check($post)){
			$filter->action();
		}
	}
	
	purge_flood_table();
}

function withhold(array $post){
	global $pdo, $board, $config;
	$query = prepare(sprintf("INSERT INTO ``withheld`` VALUES ( NULL, :thread, :subject, :email, :name, :trip, :capcode, :body, :body_nomarkup, :time, :time, :files, :num_files, :filehash, :password, :ip, :sticky, :locked, :cycle, 0, :embed, :slug,
		:reference, :board, :poll_data)", $board['uri']));
	
	//withheld refernces
	if (!empty($post['reference'])) {
		$query->bindValue(':reference', $post['reference']);
	} else {
		$query->bindValue(':reference', null, PDO::PARAM_NULL);
	}
	if (!empty($post['board'])) {
		$query->bindValue(':board', $post['board']);
	} else {
		$query->bindValue(':board', null, PDO::PARAM_NULL);
	}
		
	// Basic stuff
	if (!empty($post['subject'])) {
		$query->bindValue(':subject', $post['subject']);
	} else {
		$query->bindValue(':subject', null, PDO::PARAM_NULL);
	}

	if (!empty($post['email'])) {
		$query->bindValue(':email', $post['email']);
	} else {
		$query->bindValue(':email', null, PDO::PARAM_NULL);
	}

	if (!empty($post['trip'])) {
		$query->bindValue(':trip', $post['trip']);
	} else {
		$query->bindValue(':trip', null, PDO::PARAM_NULL);
	}

	$query->bindValue(':name', $post['name']);
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup']);
	$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
	$query->bindValue(':password', $post['password']);		
	$query->bindValue(':ip', isset($post['ip']) ? $post['ip'] : $_SERVER['REMOTE_ADDR']);
	$config['poll_board'] ? $query->bindValue(':poll_data', $post['poll_data']) : $query->bindValue(':poll_data', null) ;	

	if ($post['op'] && $post['mod'] && isset($post['sticky']) && $post['sticky']) {
		$query->bindValue(':sticky', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':sticky', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $post['mod'] && isset($post['locked']) && $post['locked']) {
		$query->bindValue(':locked', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':locked', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $post['mod'] && isset($post['cycle']) && $post['cycle']) {
		$query->bindValue(':cycle', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':cycle', false, PDO::PARAM_INT);
	}

	if ($post['mod'] && isset($post['capcode']) && $post['capcode']) {
		$query->bindValue(':capcode', $post['capcode'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':capcode', null, PDO::PARAM_NULL);
	}

	if (!empty($post['embed'])) {
		$query->bindValue(':embed', $post['embed']);
	} else {
		$query->bindValue(':embed', null, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		// No parent thread, image
		$query->bindValue(':thread', null, PDO::PARAM_NULL);
	} else {
		$query->bindValue(':thread', $post['thread'], PDO::PARAM_INT);
	}

	if ($post['has_file']) {
		$query->bindValue(':files', json_encode($post['files']));
		$query->bindValue(':num_files', $post['num_files']);
		$query->bindValue(':filehash', $post['filehash']);
	} else {
		$query->bindValue(':files', null, PDO::PARAM_NULL);
		$query->bindValue(':num_files', 0);
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		$query->bindValue(':slug', slugify($post));
	}
	else {
		$query->bindValue(':slug', NULL);
	}

	if (!$query->execute()) {
		undoImage($post);
		error(db_error($query));
	}

	return $pdo->lastInsertId();
}

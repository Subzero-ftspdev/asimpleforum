<?php

namespace Model;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserModel extends BaseModel
{
	/**
	 * Silex App
	 * @var object
	 */
	public $app;

	/**
	 * Set the silex app object
	 * @param SilexApplication $app
	 */
	public function __construct (\Silex\Application $app)
	{
		$this->app = $app;
	}

	public function find_by_username ($username)
	{
		if (!$username)
		{
			return false;
		}

		$cache_key = 'user-' . $username;
		$user = $this->app['cache']->get($cache_key, function () use ($username) {
			$data = array(
				'data' => $this->app['db']->fetchAssoc('SELECT id,username,ip,regdate,topics,posts FROM users WHERE username=? LIMIT 1', array(
					$username
				))
			);

			return $data;
		});

		$profile = $this->app['db']->fetchAssoc('SELECT * FROM profiles WHERE id=? LIMIT 1', array(
			$user['data']['id']
		));

		$user['data']['profile'] = $profile;

		return $user;
	}

	public function update_views (Request $request) 
	{
		$user_id = (int) $request->get('userId');

		if (!$user_id)
		{
			return false;
		}

		$this->app['db']->executeQuery('UPDATE profiles SET views=views+1 WHERE id=? LIMIT 1', array(
			$user_id
		));

		return true;
	}

	public function find_comments (Request $request)
	{
		$user_id = (int) $request->get('user_id');
		$page = (int) $request->get('page');

		if (!$user_id)
		{
			return false;
		}

		$cache_key = 'profile-comments-' . $user_id . '.' . $page;

		$comments['data'] = $this->app['cache']->get($cache_key, function () use ($user_id, $page) {
			$data = array(
				'data' => $this->app['db']->fetchAll('SELECT p.*, u.username FROM profile_comments p LEFT JOIN users u ON u.id=p.author WHERE p.profile=? ORDER BY p.added DESC LIMIT ' . (($page - 1)* 5) . ', 5', array(
					$user_id
				))
			);

			return $data;
		});

		foreach ($comments['data']['data'] as $key => $comment)
		{
			$cache_key = 'profile-comment-' . $comment['id'] . '-likes';

			$likes = $this->app['cache']->get($cache_key, function () use ($comments, $key, $comment) {
				$data = array(
					'data' => $this->app['db']->fetchAll('SELECT username FROM profile_comment_likes WHERE profile_comment=? ORDER BY added DESC', array(
						$comment['id']
					))
				);

				return $data;
			});

			if (!$likes['data'])
			{
				$comments['data']['data'][$key]['likes'] = array();
			} 
			else
			{
				foreach ($likes['data'] as $like)
				{
					$comments['data']['data'][$key]['likes'][] = $like['username'];
				}
			}
			
		}

		return json_encode($comments);
	}

	public function addComment (Request $request)
	{
		$response = new Response;

		$profile_id = (int) $request->get('profileId');
		$comment = $request->get('comment');

		$user = $this->app['session']->get('user');

		if (!$user)
		{
			$response->setStatusCode(403);
			$response->setContent($this->app['language']->phrase('MUST_BE_LOGGED_IN'));
			return $response;
		}

		if (!$comment || strlen($comment) < 6)
		{
			$response->setStatusCode(400);
			$response->setContent($this->app['language']->phrase('COMMENT_MIN_LENGTH', array(6)));
			return $response;
		}

		// Todo: Add time limit
		$time = time();

		$this->app['db']->insert('profile_comments', array(
			'profile' => $profile_id,
			'author' => $user['id'],
			'comment' => $comment,
			'added' => $time
		));

		$this->app['cache']->delete_group('profile-comments-' . $profile_id);

		return json_encode(array(
			'comment' => $comment,
			'added' => $time,
			'username' => $user['username'],
			'profileId' => $profile_id
		));
	}

	public function likeComment (Request $request)
	{
		$comment_id = (int) $request->get('commentId');
		$username = $request->get('username');

		$response = new Response;

		if (!$comment_id)
		{
			$response->setStatusCode(500);
			$response->setContent($this->app['language']->phrase('UNKNOWN_ERROR'));
			return $response;
		}

		// Check if liked already
		$check = $this->app['db']->fetchColumn('SELECT username FROM profile_comment_likes WHERE username=? LIMIT 1', array(
			$username
		));

		if ($check)
		{
			$response->setStatusCode(400);
			$response->setContent($this->app['language']->phrase('ALREADY_LIKED'));
			return $response;
		}

		$this->app['db']->insert('profile_comment_likes', array(
			'profile_comment' => $comment_id,
			'username' => $username,
			'added' => time()
		));

		$cache_key = 'profile-comment-' . $comment_id . '-likes';
		$this->app['cache']->delete($cache_key);

		return true;
	}
}
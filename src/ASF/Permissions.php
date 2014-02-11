<?php

namespace ASF;

/**
 * A bitwise permission system
 */
class Permissions
{
	/**
	 * The Silex Applicatio
	 * @var object
	 */
	public static $app;

	const CREATE_POST = 1; // includes delete

	const CREATE_TOPIC = 2; // includes delete

	const EDIT_POSTS = 4; // includes delete

	const EDIT_TOPICS = 8; // includes delete

	const LOCK_TOPIC = 16;

	const STICKY_TOPIC = 16;

	const BYPASS_RESTRICTIONS = 32;

	const ADD_FORUMS = 64;

	const ACCESS_ADMIN = 128;

	/**
	 * Checks to see if a user has permission for a given action
	 * @param  string  $action Corrosponds to the class constants
	 * @return boolean
	 */
	public static function hasPermission ($action)
	{
		$user = self::$app['session']->get('user');

		if (!$user)
		{
			return false;
		}

		$constant = constant('self::' . $action);

		return (int) $user['group']['permission'] & $constant;
	}

	/**
	 * Sets permissions for a user
	 * @param array $permissions
	 *
	 * @todo Not really needed so can be removed
	 */
	public static function setPermissions (array $permissions)
	{
		$_perms = null;

		foreach ($permissions as $permission)
		{
			$const = constant('self::' . $permission);
			
			if ($_perms === null)
			{
				$_perms = $const;
			}
			else
			{
				$_perms |= $const;
			}
		}

		return $_perms;
	}
}
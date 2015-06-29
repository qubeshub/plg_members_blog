<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access
defined('_HZEXEC_') or die();

/**
 * Members Plugin class for blog entries
 */
class plgMembersBlog extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 */
	protected $_autoloadLanguage = true;

	/**
	 * Return the alias and name for this category of content
	 *
	 * @return     array
	 */
	public function &onMembersAreas($user, $member)
	{
		$areas = array(
			'blog' => Lang::txt('PLG_MEMBERS_BLOG'),
			'icon' => 'f075'
		);
		return $areas;
	}

	/**
	 * Perform actions when viewing a member profile
	 *
	 * @param      object $user   Current user
	 * @param      object $member Current member page
	 * @param      string $option Start of records to pull
	 * @param      array  $areas  Active area(s)
	 * @return     array
	 */
	public function onMembers($user, $member, $option, $areas)
	{
		$returnhtml = true;

		// Check if our area is in the array of areas we want to return results for
		if (is_array($areas))
		{
			if (!array_intersect($areas, $this->onMembersAreas($user, $member))
			 && !array_intersect($areas, array_keys($this->onMembersAreas($user, $member))))
			{
				$returnhtml = false;
			}
		}

		$arr = array(
			'html'     => '',
			'metadata' => ''
		);

		include_once(PATH_CORE . DS . 'components' . DS . 'com_blog' . DS . 'models' . DS . 'archive.php');

		// Get our model
		$this->model = new \Components\Blog\Models\Archive('member', $member->get('uidNumber'));

		if ($returnhtml)
		{
			$this->user    = $user;
			$this->member  = $member;
			$this->option  = $option;
			//$this->authorized = $authorized;
			$this->database = App::get('db');

			$p = new \Hubzero\Plugin\Params($this->database);
			$this->params = $p->getParams($this->member->get('uidNumber'), 'members', $this->_name);

			if ($user->get('id') == $member->get('uidNumber'))
			{
				$this->params->set('access-edit-comment', true);
				$this->params->set('access-delete-comment', true);
			}

			// Append to document the title
			Document::setTitle(Document::getTitle() . ': ' . Lang::txt('PLG_MEMBERS_BLOG'));

			// Get and determine task
			$this->task = Request::getVar('action', '');

			if (!($task = Request::getVar('action', '', 'post')))
			{
				$bits = $this->_parseUrl();
				if ($this->task != 'deletecomment')
				{
					$num = count($bits);
					switch ($num)
					{
						case 3:
							$this->task = 'entry';
						break;

						case 2:
						case 1:
							if (is_numeric($bits[0]))
							{
								$this->task = 'browse';
							}
						break;
					}
				}
			}
			else
			{
				$this->task = $task;
			}

			switch ($this->task)
			{
				// Feeds
				case 'feed.rss': $this->_feed();   break;
				case 'feed':     $this->_feed();   break;
				//case 'comments.rss': $this->_commentsFeed();   break;
				//case 'comments':     $this->_commentsFeed();   break;

				// Settings
				case 'savesettings': $arr['html'] = $this->_savesettings(); break;
				case 'settings':     $arr['html'] = $this->_settings();     break;

				// Comments
				case 'savecomment':   $arr['html'] = $this->_savecomment();   break;
				case 'newcomment':    $arr['html'] = $this->_newcomment();    break;
				case 'editcomment':   $arr['html'] = $this->_entry();         break;
				case 'deletecomment': $arr['html'] = $this->_deletecomment(); break;

				// Entries
				case 'save':   $arr['html'] = $this->_save();   break;
				case 'new':    $arr['html'] = $this->_new();    break;
				case 'edit':   $arr['html'] = $this->_edit();   break;
				case 'delete': $arr['html'] = $this->_delete(); break;
				case 'entry':  $arr['html'] = $this->_entry();  break;

				case 'archive':
				case 'browse':
				default: $arr['html'] = $this->_browse(); break;
			}
		}

		// Build filters
		$filters = array(
			'scope'      => 'member',
			'scope_id'   => $member->get('uidNumber'),
			'created_by' => $member->get('uidNumber')
		);

		if (User::isGuest())
		{
			$filters['state'] = 'public';
		}
		// Logged-in non-owner
		else if (User::get('id') != $member->get('uidNumber'))
		{
			$filters['state'] = 'registered';
		}
		// Owner of the blog
		else
		{
			$filters['state'] = 'all';
			$filters['authorized'] = $member->get('uidNumber');
		}

		// Get an entry count
		$arr['metadata']['count'] = $this->model->entries('count', $filters);

		return $arr;
	}

	/**
	 * Parse an SEF URL into its component bits
	 * stripping out the path leading up to the blog plugin
	 *
	 * @return     string
	 */
	private function _parseUrl()
	{
		static $path;

		if (!$path)
		{
			$path = Request::path();
			$path = str_replace(Request::base(true), '', $path);
			$path = str_replace('index.php', '', $path);
			$path = '/' . trim($path, '/');

			$blog = '/members/' . $this->member->get('uidNumber') . '/' . $this->_name;

			if ($path == $blog)
			{
				$path = array();
				return $path;
			}

			$path = ltrim($path, '/');
			$path = explode('/', $path);

			/*while ($path[0] != 'members' && !empty($path));
			{
				array_shift($path);
			}*/
			$paths = array();
			$start = false;
			foreach ($path as $bit)
			{
				if ($bit == $this->_name && !$start)
				{
					$start = true;
					continue;
				}
				if ($start)
				{
					$paths[] = $bit;
				}
			}
			/*if (count($paths) >= 1)
			{
				//array_shift($paths);  // Remove member ID
				array_shift($paths);  // Remove 'blog'
			}*/
			$path = $paths;
		}

		return $path;
	}

	/**
	 * Display a list of latest blog entries
	 *
	 * @return     string
	 */
	private function _browse()
	{
		$view = $this->view('default', 'browse');
		$view->option = $this->option;
		$view->member = $this->member;
		$view->config = $this->params;
		$view->model  = $this->model;

		// Filters for returning results
		$view->filters = array(
			'limit'      => Request::getInt('limit', Config::get('list_limit')),
			'start'      => Request::getInt('limitstart', 0),
			'created_by' => $this->member->get('uidNumber'),
			'year'       => Request::getInt('year', 0),
			'month'      => Request::getInt('month', 0),
			'scope'      => 'member',
			'scope_id'   => $this->member->get('uidNumber'),
			'search'     => Request::getVar('search',''),
			'authorized' => false
		);

		// See what information we can get from the path
		$path = Request::path();
		if (strstr($path, '/'))
		{
			$bits = $this->_parseUrl();

			$view->filters['year']  = (isset($bits[0]) && is_numeric($bits[0])) ? $bits[0] : $view->filters['year'];
			$view->filters['month'] = (isset($bits[1]) && is_numeric($bits[1])) ? $bits[1] : $view->filters['month'];
		}

		// Check logged-in status
		if (User::isGuest())
		{
			$view->filters['state'] = 'public';
		}
		// Logged-in non-owner
		else if (User::get('id') != $this->member->get('uidNumber'))
		{
			$view->filters['state'] = 'registered';
		}
		// Owner of the blog
		else
		{
			$view->filters['state'] = 'all';
		}
		if (User::get('id') == $this->member->get('uidNumber'))
		{
			$view->filters['authorized'] = $this->member->get('uidNumber');
		}

		$view->year   = $view->filters['year'];
		$view->month  = $view->filters['month'];
		$view->search = $view->filters['search'];

		foreach ($this->getErrors() as $error)
		{
			$view->setError($error);
		}

		return $view->loadTemplate();
	}

	/**
	 * Display an RSS feed of latest entries
	 *
	 * @return     string
	 */
	private function _feed()
	{
		if (!$this->params->get('feeds_enabled', 1))
		{
			$this->_browse();
			return;
		}

		include_once(PATH_CORE . DS . 'libraries' . DS . 'joomla' . DS . 'document' . DS . 'feed' . DS . 'feed.php');

		// Set the mime encoding for the document
		Document::setType('feed');

		// Start a new feed object
		$doc = Document::instance();
		$doc->link = Route::url($this->member->getLink() . '&active=' . $this->_name);

		// Filters for returning results
		$filters = array(
			'limit'      => Request::getInt('limit', Config::get('list_limit')),
			'start'      => Request::getInt('limitstart', 0),
			'year'       => Request::getInt('year', 0),
			'month'      => Request::getInt('month', 0),
			'scope'      => 'member',
			'scope_id'   => $this->member->get('uidNumber'),
			'search'     => Request::getVar('search',''),
			'created_by' => $this->member->get('uidNumber')
		);

		$path = Request::path();
		if (strstr($path, '/'))
		{
			$bits = $this->_parseUrl();

			$filters['year']  = (isset($bits[0]) && is_numeric($bits[0])) ? $bits[0] : $filters['year'];
			$filters['month'] = (isset($bits[1]) && is_numeric($bits[1])) ? $bits[1] : $filters['month'];
		}

		// Build some basic RSS document information
		$doc->title       = Config::get('sitename') . ' - ' . stripslashes($this->member->get('name')) . ': ' . Lang::txt('Blog');
		$doc->description = Lang::txt('PLG_MEMBERS_BLOG_RSS_DESCRIPTION', Config::get('sitename'),stripslashes($this->member->get('name')));
		$doc->copyright   = Lang::txt('PLG_MEMBERS_BLOG_RSS_COPYRIGHT', date("Y"), Config::get('sitename'));
		$doc->category    = Lang::txt('PLG_MEMBERS_BLOG_RSS_CATEGORY');

		$filters['state'] = 'public';

		$rows = $this->model->entries('list', $filters);

		// Start outputing results if any found
		if ($rows->total() > 0)
		{
			foreach ($rows as $row)
			{
				$item = new \Hubzero\Document\Type\Feed\Item();

				// Strip html from feed item description text
				$item->description = $row->content('parsed');
				$item->description = html_entity_decode(\Hubzero\Utility\Sanitize::stripAll($item->description));
				if ($this->params->get('feed_entries') == 'partial')
				{
					$item->description = \Hubzero\Utility\String::truncate($item->description, 300);
				}

				// Load individual item creator class
				$item->title       = html_entity_decode(strip_tags($row->get('title')));
				$item->link        = Route::url($row->link());
				$item->date        = date('r', strtotime($row->published()));
				$item->category    = '';
				$item->author      = $row->creator('name');

				// Loads item info into rss array
				$doc->addItem($item);
			}
		}

		// Output the feed
		echo $doc->render();
	}

	/**
	 * Display a blog entry
	 *
	 * @return     string
	 */
	private function _entry()
	{
		$view = $this->view('default', 'entry');
		$view->option = $this->option;
		$view->member = $this->member;
		$view->config = $this->params;
		$view->model  = $this->model;

		if (isset($this->entry) && is_object($this->entry))
		{
			$view->row = $this->entry;
		}
		else
		{
			$path = Request::path();
			$alias = '';
			if (strstr($path, '/'))
			{
				$bits = $this->_parseUrl();

				$alias = end($bits);
			}

			$view->row = $this->model->entry($alias);
		}

		if (!$view->row->exists())
		{
			App::abort(404, Lang::txt('PLG_MEMBERS_BLOG_NO_ENTRY_FOUND'));
			return;
		}

		// Check authorization
		if (($view->row->get('state') == 2 && User::isGuest())
		 || ($view->row->get('state') == 0 && User::get('id') != $this->member->get('uidNumber')))
		{
			App::abort(403, Lang::txt('PLG_MEMBERS_BLOG_NOT_AUTH'));
			return;
		}

		// Filters for returning results
		$view->filters = array(
			'limit'      => 10,
			'start'      => 0,
			'scope'      => 'member',
			'scope_id'   => $this->member->get('uidNumber'),
			'created_by' => $this->member->get('uidNumber')
		);

		if (User::isGuest())
		{
			$view->filters['state'] = 'public';
		}
		else
		{
			if (User::get('id') != $this->member->get('uidNumber'))
			{
				$view->filters['state'] = 'registered';
			}
		}

		foreach ($this->getErrors() as $error)
		{
			$view->setError($error);
		}

		return $view->loadTemplate();
	}

	/**
	 * Display a warning message
	 *
	 * @return     string
	 */
	private function _login()
	{
		return '<p class="warning">' . Lang::txt('MEMBERS_LOGIN_NOTICE') . '</p>';
	}

	/**
	 * Display a form for creating an entry
	 *
	 * @return     string
	 */
	private function _new()
	{
		return $this->_edit();
	}

	/**
	 * Display a form for editing an entry
	 *
	 * @return     string
	 */
	private function _edit($row=null)
	{
		// Login check
		if (User::isGuest())
		{
			$this->setError(Lang::txt('MEMBERS_LOGIN_NOTICE'));
			return $this->_login();
		}

		if (User::get('id') != $this->member->get('uidNumber'))
		{
			$this->setError(Lang::txt('PLG_MEMBERS_BLOG_NOT_AUTHORIZED'));
			return $this->_browse();
		}

		// Instantiate view
		$view = $this->view('default', 'edit');
		$view->option = $this->option;
		$view->member = $this->member;
		$view->task   = $this->task;
		$view->config = $this->params;

		// Load the entry
		if (is_object($row))
		{
			$view->entry = $row;
		}
		else
		{
			$view->entry = new \Components\Blog\Models\Entry(Request::getInt('entry', 0));
		}

		// Does it exist?
		if (!$view->entry->exists())
		{
			// Set some defaults
			$view->entry->set('allow_comments', 1);
			$view->entry->set('state', 1);
			$view->entry->set('scope', 'member');
			$view->entry->set('scope_id', $this->member->get('uidNumber'));
			$view->entry->set('created_by', $this->member->get('uidNumber'));
		}

		// Pass any errors on to the view
		foreach ($this->getErrors() as $error)
		{
			$view->setError($error);
		}

		// Render view
		return $view->loadTemplate();
	}

	/**
	 * Save an entry
	 *
	 * @return     void
	 */
	private function _save()
	{
		// Login check
		if (User::isGuest())
		{
			$this->setError(Lang::txt('MEMBERS_LOGIN_NOTICE'));
			return $this->_login();
		}

		if (User::get('id') != $this->member->get('uidNumber'))
		{
			$this->setError(Lang::txt('PLG_MEMBERS_BLOG_NOT_AUTHORIZED'));
			return $this->_browse();
		}

		$entry = Request::getVar('entry', array(), 'post', 'none', 2);

		if (isset($entry['publish_up']) && $entry['publish_up'] != '')
		{
			$entry['publish_up']   = Date::of($entry['publish_up'], Config::get('offset'))->toSql();
		}

		if (isset($entry['publish_down']) && $entry['publish_down'] != '')
		{
			$entry['publish_down'] = Date::of($entry['publish_down'], Config::get('offset'))->toSql();
		}

		// make sure we dont want to turn off comments
		$entry['allow_comments'] = (isset($entry['allow_comments'])) ? : 0;

		// Instantiate model
		$row = new \Components\Blog\Models\Entry($entry['id']);

		// Bind data
		if (!$row->bind($entry))
		{
			$this->setError($row->getError());
			return $this->_edit($row);
		}

		// Store new content
		if (!$row->store(true))
		{
			$this->setError($row->getError());
			return $this->_edit($row);
		}

		// Process tags
		if (!$row->tag(Request::getVar('tags', '')))
		{
			$this->setError($row->getError());
			return $this->_edit($row);
		}

		$this->redirect(Route::url($row->link()));
	}

	/**
	 * Delete an entry
	 *
	 * @return     string
	 */
	private function _delete()
	{
		if (User::isGuest())
		{
			$this->setError(Lang::txt('MEMBERS_LOGIN_NOTICE'));
			return;
		}

		if (User::get('id') != $this->member->get('uidNumber'))
		{
			$this->setError(Lang::txt('PLG_MEMBERS_BLOG_NOT_AUTHORIZED'));
			return $this->_browse();
		}

		// Incoming
		$id = Request::getInt('entry', 0);
		if (!$id)
		{
			return $this->_browse();
		}

		$process    = Request::getVar('process', '');
		$confirmdel = Request::getVar('confirmdel', '');

		// Initiate a blog entry object
		$entry = new \Components\Blog\Models\Entry($id);

		// Did they confirm delete?
		if (!$process || !$confirmdel)
		{
			if ($process && !$confirmdel)
			{
				$this->setError(Lang::txt('PLG_MEMBERS_BLOG_ERROR_CONFIRM_DELETION'));
			}

			// Output HTML
			$view = new \Hubzero\Plugin\View('default', 'delete');
			$view->option = $this->option;
			$view->member = $this->member;
			$view->task   = $this->task;
			$view->config = $this->params;
			$view->entry  = $entry;
			$view->authorized = true;

			foreach ($this->getErrors() as $error)
			{
				$view->setError($error);
			}

			return $view->loadTemplate();
		}

		// Delete the entry itself
		$entry->set('state', -1);
		if (!$entry->store())
		{
			$this->setError($entry->getError());
		}

		// Return the topics list
		App::redirect(Route::url($this->member->getLink() . '&active=' . $this->_name));
	}

	/**
	 * Save a comment
	 *
	 * @return     string
	 */
	private function _savecomment()
	{
		// Ensure the user is logged in
		if (User::isGuest())
		{
			$this->setError(Lang::txt('MEMBERS_LOGIN_NOTICE'));
			return $this->_login();
		}

		// Incoming
		$comment = Request::getVar('comment', array(), 'post', 'none', 2);

		// Instantiate a new comment object and pass it the data
		$row = new \Components\Blog\Models\Comment($comment['id']);
		if (!$row->bind($comment))
		{
			$this->setError($row->getError());
			return $this->_entry();
		}

		// Store new content
		if (!$row->store(true))
		{
			$this->setError($row->getError());
			return $this->_entry();
		}

		/*
		if ($row->get('created_by') != $this->member->get('uidNumber))
		{
			$this->entry = new \Components\Blog\Models\Entry($row->get('entry_id'));

			// Build the "from" data for the e-mail
			$from = array();
			$from['name']  = Config::get('sitename').' '.Lang::txt('PLG_MEMBERS_BLOG');
			$from['email'] = Config::get('mailfrom');

			$subject = Lang::txt('PLG_MEMBERS_BLOG_SUBJECT_COMMENT_POSTED');

			// Message
			$message  = "The following comment has been posted to your blog entry:\r\n\r\n";
			$message .= stripslashes($row->content)."\r\n\r\n";
			$message .= "To view all comments on the blog entry, go to:\r\n";
			$message .= rtrim(Request::base(), '/') . '/' . ltrim(Route::url($this->entry->link() . '#comments), '/') . "\r\n";

			// Send the message
			if (!Event::trigger('xmessage.onSendMessage', array('blog_comment', $subject, $message, $from, array($this->member->get('uidNumber')), $this->option)))
			{
				$this->setError(Lang::txt('PLG_MEMBERS_BLOG_ERROR_MSG_MEMBER_FAILED'));
			}
		}
		*/

		return $this->_entry();
	}

	/**
	 * Delete a comment
	 *
	 * @return     string
	 */
	private function _deletecomment()
	{
		// Ensure the user is logged in
		if (User::isGuest())
		{
			$this->setError(Lang::txt('MEMBERS_LOGIN_NOTICE'));
			return;
		}

		// Incoming
		$id = Request::getInt('comment', 0);
		if (!$id)
		{
			return $this->_entry();
		}

		// Initiate a blog comment object
		$comment = \Components\Blog\Models\Comment::getInstance($id);

		// Delete all comments on an entry
		$comment->set('state', 2);

		// Delete the entry itself
		if (!$comment->store(false))
		{
			$this->setError($comment->getError());
		}

		// Return the topics list
		return $this->_entry();
	}

	/**
	 * Display blog settings
	 *
	 * @return     string
	 */
	private function _settings()
	{
		if (User::isGuest())
		{
			$this->setError(Lang::txt('GROUPS_LOGIN_NOTICE'));
			return;
		}

		if (User::get('id') != $this->member->get('uidNumber'))
		{
			$this->setError(Lang::txt('PLG_MEMBERS_BLOG_NOT_AUTHORIZED'));
			return $this->_browse();
		}

		// Output HTML
		$view = $this->view('default', 'settings');
		$view->option   = $this->option;
		$view->member   = $this->member;
		$view->task     = $this->task;
		$view->config   = $this->params;

		$view->settings = new \Hubzero\Plugin\Params($this->database);
		$view->settings->loadPlugin($this->member->get('uidNumber'), 'members', $this->_name);

		$view->message  = (isset($this->message)) ? $this->message : '';

		foreach ($this->getErrors() as $error)
		{
			$view->setError($error);
		}

		return $view->loadTemplate();
	}

	/**
	 * Save blog settings
	 *
	 * @return     void
	 */
	private function _savesettings()
	{
		if (User::isGuest())
		{
			$this->setError(Lang::txt('MEMBERS_LOGIN_NOTICE'));
			return;
		}

		if (User::get('id') != $this->member->get("uidNumber"))
		{
			$this->setError(Lang::txt('PLG_MEMBERS_BLOG_NOT_AUTHORIZED'));
			return $this->_browse();
		}

		// Check for request forgeries
		Request::checkToken();

		$settings = Request::getVar('settings', array(), 'post');

		$row = new \Hubzero\Plugin\Params($this->database);
		if (!$row->bind($settings))
		{
			$this->setError($row->getError());
			return $this->_entry();
		}

		$p = new \Hubzero\Config\Registry(Request::getVar('params', array(), 'post'));

		$row->params = $p->toString();

		// Check content
		if (!$row->check())
		{
			$this->setError($row->getError());
			return $this->_settings();
		}

		// Store new content
		if (!$row->store())
		{
			$this->setError($row->getError());
			return $this->_settings();
		}

		App::redirect(
			Route::url($this->member->getLink() . '&active=' . $this->_name . '&task=settings'),
			Lang::txt('PLG_MEMBERS_BLOG_SETTINGS_SAVED')
		);
	}
}

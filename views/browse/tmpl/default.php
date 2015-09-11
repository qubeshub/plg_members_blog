<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
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
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access
defined('_HZEXEC_') or die();

$live_site = rtrim(Request::base(),'/');

$first = $this->model->entries('first');

$base = $this->member->getLink() . '&active=blog';

$this->css()
     ->js();
?>

<?php if (User::get('id') == $this->member->get('uidNumber')) : ?>
<ul id="page_options">
	<li>
		<a class="icon-add add btn" href="<?php echo Route::url($base . '&task=new'); ?>">
			<?php echo Lang::txt('PLG_MEMBERS_BLOG_NEW_ENTRY'); ?>
		</a>
	</li>
	<li>
		<a class="icon-config config btn" href="<?php echo Route::url($base . '&task=settings'); ?>">
			<?php echo Lang::txt('PLG_MEMBERS_BLOG_SETTINGS'); ?>
		</a>
	</li>
</ul>
<?php endif; ?>

<?php if (User::get('id') == $this->member->get('uidNumber') && !$this->filters['year'] && !$this->filters['search'] && !$this->model->entries('count', $this->filters)) { ?>

	<div class="introduction">
		<div class="introduction-message">
			<p><?php echo Lang::txt('PLG_MEMBERS_BLOG_INTRO_EMPTY'); ?></p>
		</div>
		<div class="introduction-questions">
			<p><strong><?php echo Lang::txt('PLG_MEMBERS_BLOG_INTRO_WHAT_IS_A_BLOG'); ?></strong></p>
			<p><?php echo Lang::txt('PLG_MEMBERS_BLOG_INTRO_WHAT_IS_A_BLOG_EXPLANATION'); ?></p>

			<p><strong><?php echo Lang::txt('PLG_MEMBERS_BLOG_INTRO_HOW_TO_START'); ?></strong></p>
			<p><?php echo Lang::txt('PLG_MEMBERS_BLOG_INTRO_HOW_TO_START_EXPLANATION'); ?></p>
		</div>
	</div><!-- / .introduction -->

<?php } else { ?>

<form method="get" action="<?php echo Route::url($base); ?>">
	<section class="section">
		<div class="subject">
			<?php if ($this->getError()) : ?>
				<p class="error"><?php echo $this->getError(); ?></p>
			<?php endif; ?>

			<div class="container data-entry">
				<input class="entry-search-submit" type="submit" value="<?php echo Lang::txt('PLG_MEMBERS_BLOG_SEARCH'); ?>" />
				<fieldset class="entry-search">
					<legend><?php echo Lang::txt('PLG_MEMBERS_BLOG_SEARCH_LEGEND'); ?></legend>
					<label for="entry-search-field"><?php echo Lang::txt('PLG_MEMBERS_BLOG_SEARCH_LABEL'); ?></label>
					<input type="text" name="search" id="entry-search-field" value="<?php echo $this->escape(utf8_encode(stripslashes($this->search))); ?>" placeholder="<?php echo Lang::txt('PLG_MEMBERS_BLOG_SEARCH_PLACEHOLDER'); ?>" />
				</fieldset>
			</div><!-- / .container -->

			<div class="container">
				<h3>
					<?php if (isset($this->search) && $this->search) { ?>
						<?php echo Lang::txt('PLG_MEMBERS_BLOG_SEARCH_FOR', $this->escape($this->search)); ?>
					<?php } else if (!isset($this->year) || !$this->year) { ?>
						<?php echo Lang::txt('PLG_MEMBERS_BLOG_LATEST_ENTRIES'); ?>
					<?php } else {
						$archiveDate  = $this->year;
						$archiveDate .= ($this->month) ? '-' . $this->month : '-01';
						$archiveDate .= '-01 00:00:00';
						if ($this->month)
						{
							echo Date::of($archiveDate)->format('M Y');
						}
						else
						{
							echo Date::of($archiveDate)->format('Y');
						}
					} ?>
					<?php
					if ($this->config->get('feeds_enabled', 1)) {
						$path  = $base . '&task=feed.rss';
						$path .= ($this->year)  ? '&year=' . $this->year   : '';
						$path .= ($this->month) ? '&month=' . $this->month : '';
						$feed = Route::url($path);
						if (substr($feed, 0, 4) != 'http')
						{
							$feed = rtrim($live_site, DS) . DS . ltrim($feed, DS);
						}
						$feed = str_replace('https:://', 'http://', $feed);
					?>
					<a class="feed" href="<?php echo $feed; ?>">
						<?php echo Lang::txt('PLG_MEMBERS_BLOG_RSS_FEED'); ?>
					</a>
					<?php } ?>
				</h3>

			<?php
			$rows = $this->model->entries('list', $this->filters);
			if ($rows->total()) { ?>
				<ol class="blog-entries entries">
				<?php
				$cls = 'even';
				foreach ($rows as $row)
				{
					$cls = ($cls == 'even') ? 'odd' : 'even';

					if ($row->ended())
					{
						$cls .= ' expired';
					}
					?>
					<li class="<?php echo $cls; ?>" id="e<?php echo $row->get('id'); ?>">
						<article>
							<h4 class="entry-title">
								<a href="<?php echo Route::url($row->link()); ?>">
									<?php echo $this->escape(stripslashes($row->get('title'))); ?>
								</a>
							</h4>
							<dl class="entry-meta">
								<dt>
									<span>
										<?php echo Lang::txt('PLG_MEMBERS_BLOG_ENTRY_NUMBER', $row->get('id')); ?>
									</span>
								</dt>
								<dd class="date">
									<time datetime="<?php echo $row->published(); ?>">
										<?php echo $row->published('date'); ?>
									</time>
								</dd>
								<dd class="time">
									<time datetime="<?php echo $row->published(); ?>">
										<?php echo $row->published('time'); ?>
									</time>
								</dd>
								<dd class="author">
									<?php if ($row->creator('public')) { ?>
										<a href="<?php echo Route::url($row->creator()->getLink()); ?>">
											<?php echo $this->escape(stripslashes($row->creator('name'))); ?>
										</a>
									<?php } else { ?>
										<?php echo $this->escape(stripslashes($row->creator('name'))); ?>
									<?php } ?>
								</dd>
								<?php if ($row->get('allow_comments') == 1) { ?>
									<dd class="comments">
										<a href="<?php echo Route::url($row->link('comments')); ?>">
											<?php echo Lang::txt('PLG_MEMBERS_BLOG_NUM_COMMENTS', $row->get('comments', 0)); ?>
										</a>
									</dd>
								<?php } else { ?>
									<dd class="comments">
										<span>
											<?php echo Lang::txt('PLG_MEMBERS_BLOG_COMMENTS_OFF'); ?>
										</span>
									</dd>
								<?php } ?>
								<?php if (User::get('id') == $row->get('created_by')) { ?>
									<dd class="state <?php echo $row->state('text'); ?>">
										<?php echo Lang::txt('PLG_MEMBERS_BLOG_STATE_' . strtoupper($row->state('text'))); ?>
									</dd>
								<?php } ?>
								<dd class="entry-options">
								<?php if (User::get('id') == $row->get('created_by')) { ?>
									<a class="edit" href="<?php echo Route::url($row->link('edit')); ?>" title="<?php echo Lang::txt('PLG_MEMBERS_BLOG_EDIT'); ?>">
										<?php echo Lang::txt('PLG_MEMBERS_BLOG_EDIT'); ?>
									</a>
									<a class="delete" data-confirm="<?php echo Lang::txt('PLG_MEMBERS_BLOG_CONFIRM_DELETE'); ?>" href="<?php echo Route::url($row->link('delete')); ?>" title="<?php echo Lang::txt('PLG_MEMBERS_BLOG_DELETE'); ?>">
										<?php echo Lang::txt('PLG_MEMBERS_BLOG_DELETE'); ?>
									</a>
								<?php } ?>
								</dd>
							</dl>
							<div class="entry-content">
								<?php if ($this->config->get('cleanintro', 1)) { ?>
									<p>
										<?php echo $row->content('clean', $this->config->get('introlength', 300)); ?>
									</p>
								<?php } else { ?>
									<?php echo $row->content('parsed', $this->config->get('introlength', 300)); ?>
								<?php } ?>
							</div>
						</article>
					</li>
				<?php } ?>
				</ol>
				<?php
					$pageNav = $this->pagination(
						$this->model->entries('count', $this->filters),
						$this->filters['start'],
						$this->filters['limit']
					);
					$pageNav->setAdditionalUrlParam('id', $this->member->get('uidNumber'));
					$pageNav->setAdditionalUrlParam('active', 'blog');
					if ($this->filters['year'])
					{
						$pageNav->setAdditionalUrlParam('year', $this->filters['year']);
					}
					if ($this->filters['month'])
					{
						$pageNav->setAdditionalUrlParam('month', $this->filters['month']);
					}
					if ($this->filters['search'])
					{
						$pageNav->setAdditionalUrlParam('search', $this->filters['search']);
					}
					echo $pageNav->render();
				?>
			<?php } else { ?>
				<p class="warning"><?php echo Lang::txt('PLG_MEMBERS_BLOG_NO_ENTRIES_FOUND'); ?></p>
			<?php } ?>
			</div>
		</div><!-- / .subject -->
		<aside class="aside">
			<?php if ($first->exists()) { ?>
				<div class="container">
					<h4><?php echo Lang::txt('PLG_MEMBERS_BLOG_ENTRIES_BY_YEAR'); ?></h4>
					<ul>
						<?php
							$start = intval(substr($first->get('publish_up'), 0, 4));
							$now = date("Y");
							$m = array(
								'JANUARY',
								'FEBRUARY',
								'MARCH',
								'APRIL',
								'MAY',
								'JUNE',
								'JULY',
								'AUGUST',
								'SEPTEMBER',
								'OCTOBER',
								'NOVEMBER',
								'DECEMBER'
							);
						?>
						<?php for ($i=$now, $n=$start; $i >= $n; $i--) : ?>
							<li>
								<a href="<?php echo Route::url($base . '&task=' . $i); ?>">
									<?php echo $i; ?>
								</a>
								<?php if (($this->year && $i == $this->year) || (!$this->year && $i == $now)) : ?>
									<ul>
										<?php $months = ($i == $now) ? date("m") : 12; ?>
										<?php for ($k=0, $z=$months; $k < $z; $k++) : ?>
											<li>
												<a<?php if ($this->month && $this->month == ($k+1)) { echo ' class="active"'; } ?> href="<?php echo Route::url($base . '&task=' . $i . '/' . sprintf("%02d", ($k+1), 1)); ?>">
													<?php echo Lang::txt($m[$k]); ?>
												</a>
											</li>
										<?php endfor; ?>
									</ul>
								<?php endif; ?>
							</li>
						<?php endfor; ?>
					</ul>
				</div>
			<?php } ?>

			<?php
			$limit = $this->filters['limit'];
			$this->filters['limit'] = 5;
			?>
			<div class="container blog-popular-entries">
				<h4><?php echo Lang::txt('PLG_MEMBERS_BLOG_POPULAR_ENTRIES'); ?></h4>
				<?php
				$popular = $this->model->entries('popular', $this->filters);
				if ($popular->count()) { ?>
					<ol>
					<?php foreach ($popular as $row) { ?>
						<li>
							<a href="<?php echo Route::url($row->link()); ?>">
								<?php echo $this->escape(stripslashes($row->get('title'))); ?>
							</a>
						</li>
					<?php } ?>
					</ol>
				<?php } else { ?>
					<p><?php echo Lang::txt('PLG_MEMBERS_BLOG_NO_ENTRIES_FOUND'); ?></p>
				<?php } ?>
			</div><!-- / .blog-popular-entries -->

			<div class="container blog-recent-entries">
				<h4><?php echo Lang::txt('PLG_MEMBERS_BLOG_RECENT_ENTRIES'); ?></h4>
				<?php
				$recent = $this->model->entries('recent', $this->filters);
				if ($recent->count()) { ?>
					<ol>
					<?php foreach ($recent as $row) { ?>
						<li>
							<a href="<?php echo Route::url($row->link()); ?>">
								<?php echo $this->escape(stripslashes($row->get('title'))); ?>
							</a>
						</li>
					<?php } ?>
					</ol>
				<?php } else { ?>
					<p><?php echo Lang::txt('PLG_MEMBERS_BLOG_NO_ENTRIES_FOUND'); ?></p>
				<?php } ?>
			</div><!-- / .blog-recent-entries -->
			<?php
			$this->filters['limit'] = $limit;
			?>
		</aside><!-- / .aside -->
	</section>
</form>

<?php } ?>
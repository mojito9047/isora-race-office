<?php

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Isora\Component\Isorairc\Site\View\Entries\HtmlView $this */

$app    = Factory::getApplication();
$format = 'd M Y H:i';
$checks = 0;

foreach ($this->entries as $entry) {
    $checks += \in_array($entry->rating_status, ['check', 'none'], true) ? 1 : 0;
}
?>
<style>
	/* The site template does not style Bootstrap buttons, so these carry their own look. */
	.com-isorairc-entries .isorairc-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin: 12px 0; }
	.com-isorairc-entries a.isorairc-button {
		display: inline-block; padding: 8px 16px; border: 1px solid #b8560f; border-radius: 4px;
		background: #e8731a; color: #fff; font-weight: 700; line-height: 1.3; text-decoration: none; white-space: nowrap;
	}
	.com-isorairc-entries a.isorairc-button:hover,
	.com-isorairc-entries a.isorairc-button:focus { background: #b8560f; color: #fff; text-decoration: none; }
	.com-isorairc-entries a.isorairc-button:focus-visible { outline: 3px solid #333; outline-offset: 2px; }
	/* Second-line details inside a cell, so the race management view fits the page without sideways scrolling. */
	.com-isorairc-entries .isorairc-detail { display: block; font-size: 0.9em; opacity: 0.85; }
	.com-isorairc-entries .isorairc-flag { font-weight: 700; opacity: 1; }
	/* The Boat data button is a small form (the RORC page only takes a posted search), drawn as a link. */
	.com-isorairc-entries form.isorairc-boatdata { display: block; margin: 0; }
	.com-isorairc-entries form.isorairc-boatdata button {
		padding: 0; border: 0; background: none; color: #b8560f; font: inherit; font-size: 0.9em; text-decoration: underline; cursor: pointer;
	}
	.com-isorairc-entries form.isorairc-boatdata button:hover,
	.com-isorairc-entries form.isorairc-boatdata button:focus { color: #333; }
	.com-isorairc-entries .isorairc-contact { min-width: 18em; overflow-wrap: anywhere; }
	.com-isorairc-entries table th, .com-isorairc-entries table td { vertical-align: top; }
</style>
<div class="com-isorairc-entries">
	<?php if (!$this->configured) : ?>
		<p>The entry form has not been chosen yet. Set it under ISORA Race Office &rarr; Options &rarr; Entry form.</p>
	<?php elseif (!$this->races) : ?>
		<p>No races are linked to the entry form yet.</p>
	<?php else : ?>
		<form action="<?php echo Route::_('index.php?option=com_isorairc&view=entries'); ?>" method="get" class="mb-3">
			<?php if (!$app->get('sef')) : ?>
				<input type="hidden" name="option" value="com_isorairc">
				<input type="hidden" name="view" value="entries">
				<input type="hidden" name="Itemid" value="<?php echo $app->getInput()->getInt('Itemid'); ?>">
			<?php endif; ?>
			<label for="isorairc-entries-race" class="form-label">Race</label>
			<select id="isorairc-entries-race" name="race" class="form-select" onchange="this.form.submit()">
				<?php foreach ($this->races as $race) : ?>
					<option value="<?php echo (int) $race->id; ?>"<?php echo (int) $race->id === (int) $this->race->id ? ' selected' : ''; ?>>
						<?php echo $this->escape($race->name); ?> &ndash; <?php echo HTMLHelper::_('date', $race->race_date, 'd M Y', 'UTC'); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="btn btn-primary mt-2">Show entries</button></noscript>
		</form>

		<h2 class="h4"><?php echo $this->escape($this->race->name); ?></h2>
		<p>
			<strong><?php echo count($this->entries); ?></strong> <?php echo count($this->entries) === 1 ? 'boat' : 'boats'; ?> entered<?php
			if ($this->classCounts) {
				$parts = [];

				foreach ($this->classCounts as $class => $count) {
					$parts[] = $this->escape($class) . ' ' . (int) $count;
				}

				echo ': ' . implode(', ', $parts);
			}
			?>.
			Race date <?php echo HTMLHelper::_('date', $this->race->race_date, 'd M Y', 'UTC'); ?>;
			rating deadline <?php echo HTMLHelper::_('date', $this->race->rating_deadline, $format); ?>.
		</p>
		<p class="text-muted small">
			<?php foreach ($this->systems as $scheme => $system) : ?>
				<?php echo $this->escape($system['label']); ?> ratings:
				<?php echo $this->sources[$scheme] === 'snapshot' ? 'as frozen at the rating deadline.' : 'current list; they are frozen at the rating deadline.'; ?>
			<?php endforeach; ?>
		</p>

		<?php if ($this->canManage) : ?>
			<p class="isorairc-actions">
				<a class="isorairc-button" href="<?php echo Route::_('index.php?option=com_isorairc&task=entries.export&race=' . (int) $this->race->id . '&' . Session::getFormToken() . '=1'); ?>">Download CSV</a>
				<a class="isorairc-button" href="<?php echo Route::_('index.php?option=com_isorairc&task=entries.fleetsheet&race=' . (int) $this->race->id . '&' . Session::getFormToken() . '=1'); ?>">Download YB fleetsheet</a>
				<?php if ($checks) : ?>
					<span><?php echo (int) $checks; ?> <?php echo $checks === 1 ? 'entry needs its' : 'entries need their'; ?> rating checked (see the note under its rating).</span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if (!$this->entries) : ?>
			<p>No entries for this race yet.</p>
		<?php else : ?>
			<div class="table-responsive">
				<table class="table table-striped">
					<thead>
						<tr>
							<th scope="col">Boat</th>
							<th scope="col">Sail no</th>
							<th scope="col">Type</th>
							<th scope="col">Class</th>
							<th scope="col">Club</th>
							<th scope="col">Rating</th>
							<?php if ($this->canManage) : ?>
								<th scope="col">Contact</th>
								<th scope="col">Entry</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->entries as $entry) : ?>
							<tr>
								<th scope="row"><?php echo $this->escape($entry->boat); ?></th>
								<td><?php echo $this->escape($entry->sail); ?></td>
								<td><?php echo $this->escape($entry->model); ?></td>
								<td><?php echo $this->escape($entry->class); ?></td>
								<td><?php echo $this->escape($entry->club); ?></td>
								<td>
									<?php if ($entry->rating_text !== '') : ?>
										<?php echo $this->escape($entry->rating_text); ?><?php echo $entry->rating_status === 'check' ? ' <span title="To be confirmed">?</span>' : ''; ?>
									<?php elseif ($entry->rating_status !== 'off') : ?>
										<span class="text-muted">Not listed</span>
									<?php endif; ?>
									<?php if ($this->canManage) : ?>
										<?php if ($entry->rating_note !== '') : ?>
											<span class="isorairc-detail isorairc-flag"><?php echo $this->escape($entry->rating_note); ?></span>
										<?php endif; ?>
										<?php if ($entry->declared !== '') : ?>
											<span class="isorairc-detail">On form: <?php echo $this->escape($entry->declared); ?></span>
										<?php endif; ?>
										<?php if ($this->boatDataUrl !== '' && $entry->scheme === 'irc' && $entry->cert_no !== '') : ?>
											<form class="isorairc-boatdata" method="post" action="<?php echo $this->escape($this->boatDataUrl); ?>" target="_blank" rel="noopener noreferrer">
												<input type="hidden" name="<?php echo EntriesHelper::BOAT_DATA_FIELD; ?>" value="<?php echo $this->escape($entry->cert_no); ?>">
												<button type="submit" title="Opens RORC's valid boat data page, searched for certificate <?php echo $this->escape($entry->cert_no); ?>">Boat data</button>
											</form>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<?php if ($this->canManage) : ?>
									<td class="isorairc-contact">
										<?php echo $this->escape($entry->contact); ?>
										<?php if ($entry->phone !== '') : ?>
											<span class="isorairc-detail"><?php echo $this->escape($entry->phone); ?></span>
										<?php endif; ?>
										<?php if ($entry->email !== '') : ?>
											<span class="isorairc-detail"><?php echo $this->escape($entry->email); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php echo $this->escape(trim(preg_replace('/\s*\(.*$/s', '', $entry->category))); ?>
										<span class="isorairc-detail">Sent <?php echo HTMLHelper::_('date', $entry->submitted, $format); ?></span>
										<?php if ($entry->tracker !== '') : ?>
											<span class="isorairc-detail">Own tracker: <?php echo $this->escape($entry->tracker); ?></span>
										<?php elseif (EntriesHelper::saidYes($entry->owntracker)) : ?>
											<span class="isorairc-detail isorairc-flag">Own tracker: no number given</span>
										<?php endif; ?>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="text-muted small">A rating followed by ? was matched on boat name or sail number only and is to be confirmed.</p>
		<?php endif; ?>
	<?php endif; ?>
</div>

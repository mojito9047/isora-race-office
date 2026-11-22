<?php

\defined('_JEXEC') or die;

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
			<p>
				<a class="btn btn-secondary btn-sm" href="<?php echo Route::_('index.php?option=com_isorairc&task=entries.export&race=' . (int) $this->race->id . '&' . Session::getFormToken() . '=1'); ?>">Download CSV</a>
				<a class="btn btn-secondary btn-sm" href="<?php echo Route::_('index.php?option=com_isorairc&task=entries.fleetsheet&race=' . (int) $this->race->id . '&' . Session::getFormToken() . '=1'); ?>">Download YB fleetsheet</a>
				<?php if ($checks) : ?>
					<span class="ms-2"><?php echo (int) $checks; ?> <?php echo $checks === 1 ? 'entry needs its' : 'entries need their'; ?> rating checked (see Rating note).</span>
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
								<th scope="col">Rating note</th>
								<th scope="col">Declared</th>
								<th scope="col">Person in charge</th>
								<th scope="col">Mobile</th>
								<th scope="col">E-mail</th>
								<th scope="col">Entry type</th>
								<th scope="col">Form sent</th>
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
								</td>
								<?php if ($this->canManage) : ?>
									<td><?php echo $this->escape($entry->rating_note); ?></td>
									<td><?php echo $this->escape($entry->declared); ?></td>
									<td><?php echo $this->escape($entry->contact); ?></td>
									<td><?php echo $this->escape($entry->phone); ?></td>
									<td><?php echo $this->escape($entry->email); ?></td>
									<td><?php echo $this->escape(trim(preg_replace('/\s*\(.*$/s', '', $entry->category))); ?></td>
									<td><?php echo HTMLHelper::_('date', $entry->submitted, $format); ?></td>
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

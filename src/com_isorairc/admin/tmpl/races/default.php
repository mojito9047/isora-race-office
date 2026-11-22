<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/** @var \Isora\Component\Isorairc\Administrator\View\Races\HtmlView $this */

$format = 'd M Y H:i';
$now    = gmdate('Y-m-d H:i:s');
?>
<form action="<?php echo Route::_('index.php?option=com_isorairc&view=races'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="alert alert-info">
		<?php if (!$this->systems) : ?>
			No rating system is switched on. Enable one under Options.
		<?php endif; ?>
		<?php foreach ($this->systems as $scheme => $system) : ?>
			<div>
				<strong><?php echo $this->escape($system['label']); ?>:</strong>
				<?php $last = $this->lastImports[$scheme]; ?>
				<?php if ($last) : ?>
					last imported <?php echo HTMLHelper::_('date', $last->imported_at, $format); ?>
					(<?php echo (int) $last->row_count; ?> boats<?php if ($last->feed_modified) : ?>,
					feed dated <?php echo HTMLHelper::_('date', $last->feed_modified, $format); ?><?php endif; ?>).
				<?php else : ?>
					not imported yet. Use "Import ratings now", then set up the daily scheduled task.
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if (!$this->items) : ?>
		<div class="alert alert-warning">No races yet. Add a race with "New".</div>
	<?php else : ?>
		<table class="table">
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col">Race</th>
					<th scope="col">Race date</th>
					<th scope="col">Rating deadline</th>
					<th scope="col">Entry form question</th>
					<th scope="col">Snapshots</th>
					<th scope="col" class="w-1">ID</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr>
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
						<th scope="row">
							<a href="<?php echo Route::_('index.php?option=com_isorairc&task=race.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape($item->name); ?>
							</a>
						</th>
						<td><?php echo HTMLHelper::_('date', $item->race_date, 'd M Y', 'UTC'); ?></td>
						<td>
							<?php echo HTMLHelper::_('date', $item->rating_deadline, $format); ?>
							<div class="small text-muted"><?php echo (int) $item->deadline_days; ?> days before the race</div>
						</td>
						<td><?php echo $this->escape((string) ($item->entry_field ?? '')); ?></td>
						<td>
							<?php foreach ($this->systems as $scheme => $system) : ?>
								<?php $snapshot = $this->snapshots[(int) $item->id][$scheme] ?? null; ?>
								<div class="mb-1">
									<strong><?php echo $this->escape($system['label']); ?>:</strong>
									<?php if ($snapshot) : ?>
										<?php echo (int) $snapshot->boat_count; ?> boats, taken
										<?php echo HTMLHelper::_('date', $snapshot->taken, $format); ?>
										&ndash;
										<a href="<?php echo Route::_('index.php?option=com_isorairc&task=races.export&id=' . (int) $item->id . '&scheme=' . $scheme); ?>">Download CSV</a>
										<div class="small text-muted"><?php echo $this->escape($snapshot->note); ?></div>
									<?php elseif ($item->rating_deadline > $now) : ?>
										<span class="text-muted">Pending (deadline not reached)</span>
									<?php else : ?>
										<span class="text-muted">Due (taken by the next import)</span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</td>
						<td><?php echo (int) $item->id; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

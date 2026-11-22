<?php

\defined('_JEXEC') or die;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/** @var \Isora\Component\Isorairc\Site\View\Ratings\HtmlView $this */

$app    = Factory::getApplication();
$format = 'd M Y H:i';
?>
<div class="com-isorairc-ratings">
	<form action="<?php echo Route::_('index.php?option=com_isorairc&view=ratings'); ?>" method="get" class="mb-3">
		<?php if (!$app->get('sef')) : ?>
			<input type="hidden" name="option" value="com_isorairc">
			<input type="hidden" name="view" value="ratings">
			<input type="hidden" name="Itemid" value="<?php echo $app->getInput()->getInt('Itemid'); ?>">
		<?php endif; ?>
		<div class="mb-2">
			<label for="isorairc-q" class="form-label">Boat name or sail number</label>
			<input type="search" id="isorairc-q" name="q" class="form-control" required minlength="2"
				value="<?php echo $this->escape($this->term); ?>">
		</div>
		<?php if ($this->races) : ?>
			<div class="mb-2">
				<label for="isorairc-race" class="form-label">Ratings as at</label>
				<select id="isorairc-race" name="race" class="form-select">
					<option value="0">Today (current ratings)</option>
					<?php foreach ($this->races as $race) : ?>
						<option value="<?php echo (int) $race->id; ?>"<?php echo (int) $race->id === $this->raceId ? ' selected' : ''; ?>>
							<?php echo $this->escape($race->name); ?> &ndash; deadline
							<?php echo HTMLHelper::_('date', $race->rating_deadline, $format); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>
		<button type="submit" class="btn btn-primary">Search</button>
	</form>

	<?php if ($this->raceId) : ?>
		<p>
			Ratings frozen at the rating deadline for
			<strong><?php echo $this->escape($this->races[$this->raceId]->name); ?></strong>
			(<?php echo HTMLHelper::_('date', $this->races[$this->raceId]->rating_deadline, $format); ?>).
		</p>
	<?php endif; ?>

	<?php if ($this->term !== '') : ?>
		<?php foreach ($this->systems as $scheme => $system) : ?>
			<?php $rows = $this->results[$scheme]; ?>
			<?php $isIrc = $scheme === 'irc'; ?>
			<h2 class="h4 mt-4"><?php echo $this->escape($system['label']); ?></h2>
			<?php if (!$this->raceId && !empty($this->lastImports[$scheme])) : ?>
				<p class="text-muted small">Current ratings, last updated <?php echo HTMLHelper::_('date', $this->lastImports[$scheme], $format); ?>.</p>
			<?php endif; ?>

			<?php if ($rows === null) : ?>
				<p>No <?php echo $this->escape($system['label']); ?> ratings were frozen for this race.</p>
			<?php elseif (!$rows) : ?>
				<p>No boats found matching &ldquo;<?php echo $this->escape($this->term); ?>&rdquo;.</p>
			<?php else : ?>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<tr>
								<th scope="col">Boat</th>
								<th scope="col">Sail no</th>
								<?php if (!$isIrc) : ?>
									<th scope="col">Type</th>
								<?php endif; ?>
								<th scope="col"><?php echo $this->escape($system['rating_label']); ?></th>
								<th scope="col"><?php echo $this->escape($system['rating2_label']); ?></th>
								<?php if ($isIrc) : ?>
									<th scope="col">Cert no</th>
								<?php else : ?>
									<th scope="col">Club</th>
								<?php endif; ?>
								<th scope="col">Issued</th>
								<th scope="col">Notes</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($rows as $row) : ?>
								<tr>
									<th scope="row"><?php echo $this->escape($row->boat_name); ?></th>
									<td><?php echo $this->escape($row->sail_no); ?></td>
									<?php if (!$isIrc) : ?>
										<td><?php echo $this->escape($row->boat_type); ?></td>
									<?php endif; ?>
									<td><?php echo RatingsHelper::formatRating($row->tcc, $system); ?></td>
									<td><?php echo RatingsHelper::formatRating($row->non_spi_tcc, $system); ?></td>
									<?php if ($isIrc) : ?>
										<td><?php echo $this->escape($row->cert_no); ?></td>
									<?php else : ?>
										<td><?php echo $this->escape($row->club); ?></td>
									<?php endif; ?>
									<td><?php echo $row->issue_date ? HTMLHelper::_('date', $row->issue_date, 'd M Y', 'UTC') : ''; ?></td>
									<td>
										<?php
										$notes = [];

										if ($row->endorsed === 'E') {
											$notes[] = 'Endorsed';
										}

										if ($row->secondary) {
											$notes[] = 'Secondary certificate';
										}

										if (strtoupper((string) $row->provisional) === 'TRUE') {
											$notes[] = 'Provisional';
										}

										echo implode(', ', $notes);
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if (count($rows) >= $this->limit) : ?>
					<p class="text-muted">Showing the first <?php echo (int) $this->limit; ?> matches; refine the search to see more.</p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

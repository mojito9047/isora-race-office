<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/** @var \Isora\Component\Isorairc\Administrator\View\Race\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>
<form action="<?php echo Route::_('index.php?option=com_isorairc&layout=edit&id=' . (int) $this->item->id); ?>"
	method="post" name="adminForm" id="adminForm" class="form-validate">
	<div class="card">
		<div class="card-body">
			<?php echo $this->form->renderFieldset('details'); ?>
			<?php if (!empty($this->item->rating_deadline)) : ?>
				<p class="text-muted">
					Current rating deadline: <?php echo HTMLHelper::_('date', $this->item->rating_deadline, 'd M Y H:i'); ?>
					(recalculated when you save). If snapshots have already been taken and you change the
					race date or the number of days, tick the race in the list and use "Rebuild snapshot".
				</p>
			<?php endif; ?>
		</div>
	</div>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

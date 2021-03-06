<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access.
defined('_HZEXEC_') or die();

$levels   = array();
$labels   = array();
$selected = array();
$txtlabel = '';

if ($this->audience && !$this->audience->isNew()) { ?>
	<div class="usagescale">
		<div class="showscale">
			<ul class="audiencelevel">
				<?php
				for ($i = 0, $n = $this->numlevels; $i <= $n; $i++)
				{
					//$lb = 'label' . $i;
					$lv = 'level' . $i;
					//$ds = 'desc' . $i;
					$level = $this->audience->{'level' . $i}();
					$levels[$lv] = $this->audience->get('level' . $i);
					$labels[$lv]['title'] = $level->title;
					$labels[$lv]['desc']  = $level->description;
					if ($this->audience->get($lv))
					{
						$selected[] = $lv;
					}
				}

				// colored circles
				foreach ($levels as $key => $value)
				{
					$class = (!$value) ? ' isoff' : '';
					$class = (!$value && $key == 'level0') ? '_isoff' : $class;
					?>
					<li class="<?php echo $key . $class; ?>"><span>&nbsp;</span></li>
					<?php
				}

				// figure out text label
				if (count($selected) == 1)
				{
					$txtlabel = $labels[$selected[0]]['title'];
				}
				else if (count($selected) > 1)
				{
					$first     = array_shift($selected);
					$first     = $labels[$first]['title'];
					$firstbits = explode("-", $first);
					$first     = array_shift($firstbits);

					$last     = end($selected);
					$last     = $labels[$last]['title'];
					$lastbits = explode("-", $last);
					$last     = end($lastbits);

					$txtlabel = $first . '-' . $last;
				}
				else
				{
					$txtlabel = Lang::txt('Tool Audience Unrated');
				}
				?>
				<li class="txtlabel"><?php echo $txtlabel; ?></li>
			</ul>
		</div>

		<?php if ($this->showtips) { ?>
			<div class="explainscale">
				<table class="skillset">
					<thead>
						<tr>
							<td colspan="2" class="combtd"><?php echo Lang::txt('Difficulty Level'); ?></td>
							<td><?php echo Lang::txt('Target Audience'); ?></td>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($labels as $key => $label) { ?>
						<tr>
							<th>
								<ul class="audiencelevel">
									<?php foreach ($labels as $ky => $val) { ?>
										<li class="<?php
											$class = ($ky != $key) ? ' isoff' : '';
											$class = ($ky != $key && $ky == 'level0') ? '_isoff' : $class;
											echo $ky . $class;
											?>"><span>&nbsp;</span></li>
									<?php } ?>
								</ul>
							</th>
							<td><?php echo $label['title']; ?></td>
							<td class="secondcol"><?php echo $label['desc']; ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
				<p class="learnmore"><a href="<?php echo $this->audiencelink; ?>"><?php echo Lang::txt('Learn more'); ?> &rsaquo;</a></p>
			</div>
		<?php } ?>
	</div>
<?php } 
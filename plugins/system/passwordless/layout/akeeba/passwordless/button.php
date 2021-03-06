<?php
/**
 * @package   AkeebaPasswordlessLogin
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Passwordless\Helper\Joomla;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;

/**
 * Passwordless Login button
 *
 * Displays the Webauthn login button which is injected in login modules
 *
 * ---------------------------------------------------------------------------------------------------------------------
 * Data passed from Joomla
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * @var   FileLayout $this         The Joomla layout renderer
 * @var   array      $displayData  The data in array format. DO NOT USE.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 * Layout specific data (they are set up in the MANDATORY CODE section)
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * @var   string     $class        The button class
 * @var   string     $image        An image file relative path (passed to JHtml::image)
 * @var   string     $icon         An icon class to be used instead of the image (if provided)
 * @var   bool       $relocate     Should I try to move the passwordless login button next to the regular login button?
 * @var   string[]   $selectors    A list of CSS selectors I will use to find the regular login button in the module.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 * Additional information for customisation of the button.
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * When doing template overrides please DO NOT remove any code between the BEGIN - MANDATORY CODE  and
 * END - MANDATORY CODE  comments below.
 *
 * Starting with PHP 5.4.0, short echo tags are always recognized and parsed regardless of the short_open_tag setting
 * in your php.ini. Since we only support *much* newer versions of PHP we can use this construct instead of regular
 * echos to keep the code easier to read.
 *
 * You can do a template override of this file by copying this file into your template's html/layouts/akeeba/passwordless
 * folder (you may have to create that folder first).
 *
 * You can have separate template overrides for the front- and the backend of your site. In fact, your overrides are
 * per template, so you can possibly have different buttons for each template. You can only have ONE template override
 * per template. If your login modules have radically different needs for their buttons set "Frontend Login Modules'
 * Names" and / or "Backend Login Modules' Names" to "none" and do template overrides for the modules. Please read the
 * documentation for information on injecting the Passwordless Login buttons and / or necessary Javascript there.
 *
 * If you change the element from a <button> to something else, e.g. <a>, you may have to do a template override for the
 * login.js Javascript file as well. This file is always loaded automatically by the
 * Akeeba\Passwordless\PluginTraits\LoginModuleButtons PHP class which you cannot override. You can do that by
 * copying /media/plg_system_passwordless/css/login.css to your template's css/plg_system_passwordless folder.
 *
 * If you want to change the look and feel of the button you need to do a template override of the CSS file button.css.
 * This file is always loaded automatically by the Akeeba\Passwordless\PluginTraits\LoginModuleButtons PHP
 * class which you cannot override. You can do that by copying /media/plg_system_passwordless/css/button.css to your
 * template's css/plg_system_passwordless folder (note the lack of a final css folder!).
 *
 * If you need to change the label of the button do not do a template override. Do a language override for the language
 * key PLG_SYSTEM_PASSWORDLESS_LOGIN_LABEL (button label) and PLG_SYSTEM_PASSWORDLESS_LOGIN_DESC (button tooltip) instead. You
 * can do that in Joomla's Language page.
 */

// BEGIN - MANDATORY CODE
extract(array_merge([
	'class'     => 'akeeba-passwordless-login-button',
	'image'     => 'plg_system_passwordless/passwordless-black.png',
	'icon'      => '',
	'relocate'  => false,
	'selectors' => [
		"#form-login-submit > button",
		"button[type=submit]",
		"[type=submit]",
		"[id*=\"submit\"]",
	],
], $displayData));

$uri = new Uri(Uri::base() . 'index.php');
$uri->setVar(Joomla::getToken(), '1');

$randomId = 'akpwl-login-' . UserHelper::genRandomPassword(12) . '-' . UserHelper::genRandomPassword(8);

$jsSelectors = implode(", ", array_map(function ($selector) {
	return '"' . addslashes($selector) . '"';
}, $selectors));

if ($relocate)
{
	$js = <<< JS

window.jQuery(document).ready(function(){
	akeeba.Passwordless.moveButton(document.getElementById('{$randomId}'), [{$jsSelectors}]); 
});

JS;
	Joomla::getApplication()->getDocument()->addScriptDeclaration($js);
}
// END - MANDATORY CODE
?>
<button class="<?= $class ?> plg_system_passwordless_login_button hasTooltip"
        title="<?= Text::_('PLG_SYSTEM_PASSWORDLESS_LOGIN_DESC') ?>"
		id="<?= $randomId ?>"
		data-passwordless-url="<?= $uri->toString() ?>"
>
	<?php if (!empty($icon)): ?>
        <span class="<?= $icon ?>"></span>
	<?php elseif (!empty($image)): ?>
		<?= HTMLHelper::_('image', $image, Text::_('PLG_SYSTEM_PASSWORDLESS_LOGIN_DESC'), [
			'class' => 'icon',
		], true) ?>
	<?php endif; ?>
	<?= Text::_('PLG_SYSTEM_PASSWORDLESS_LOGIN_LABEL') ?>
</button>
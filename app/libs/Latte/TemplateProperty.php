<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.10.2018
 * Time: 21:06
 */

namespace App\Template;

use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Security\User;

/**
 * @property-read User $user
 * @property-read Presenter $presenter
 * @property-read Control $control
 * @property string $baseUrl
 * @property string $basePath
 * @property-read array $flashes
 */
final class TemplateProperty extends Template
{
}
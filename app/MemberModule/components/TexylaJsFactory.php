<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 05.03.2019
 * Time: 16:56
 */

namespace App\MemberModule\Components;

use Joseki\Webloader\JsMinFilter;
use WebLoader\Compiler;
use WebLoader\FileCollection;
use WebLoader\InvalidArgumentException;
use WebLoader\Nette\JavaScriptLoader;

class TexylaJsFactory {

	/** @var string */
	private $wwwDir;

	/**
	 * TexylaFactory constructor.
	 * @param string $wwwDir
	 */
	public function __construct(string $wwwDir) {
		$this->wwwDir = realpath($wwwDir);
	}

	/**
	 * @param string $script
	 * @param string $basePath
	 * @param array $plugins
	 * @return JavaScriptLoader
	 * @throws InvalidArgumentException
	 */
	public function create(string $script, string $basePath, array $plugins = []) {
		$files = new FileCollection($this->wwwDir . '/texyla/js');
		$files->addFiles(['texyla.js', 'selection.js', 'texy.js', 'buttons.js', 'cs.js', 'dom.js', 'view.js', 'window.js']);

		foreach ($plugins as $plugin) {
			$files->addFile('../plugins/' . $plugin .'/'. $plugin . '.js');
		}

		$files->addFile($this->wwwDir . '/js/' . $script . '.js');

		$compiler = Compiler::createJsCompiler($files, $this->wwwDir . '/texyla/temp');
		$compiler->addFileFilter(new JsMinFilter());

		return new JavaScriptLoader($compiler, $basePath . '/texyla/temp');
	}

}
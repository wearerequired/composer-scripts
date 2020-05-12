<?php

namespace Required\ComposerScripts\Tests;

use PHPUnit\Framework\TestCase;
use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\PackageEvents;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\CompositeRepository;
use Composer\Script\ScriptEvents;
use Required\ComposerScripts\PluginAvailabilityPlugin;

class PluginAvailabilityPluginTest extends TestCase {
	/** @var BufferIO */
	private $io;

	/** @var Composer */
	private $composer;

	/** @var Config */
	private $config;

	/** @var string */
	private $tempDir;

	/**
	 * @inheritdoc
	 */
	protected function setUp() {
		$this->tempDir = __DIR__ . '/temp';

		$this->config = new Config( false, realpath( __DIR__ . '/fixtures/local' ) );
		$this->config->merge( [
			'config' => [
				'home' => __DIR__,
			],
		] );

		$this->io       = new BufferIO();
		$this->composer = new Composer();
		$this->composer->setConfig( $this->config );
		$this->composer->setPackage( new RootPackage( 'my/project', '1.0.0', '1.0.0' ) );
		$this->composer->setPluginManager( new PluginManager( $this->io, $this->composer ) );
		$this->composer->setEventDispatcher( new EventDispatcher( $this->composer, $this->io ) );

		$this->cleanTempDir();
		mkdir( $this->tempDir );
	}

	/**
	 * @inheritdoc
	 */
	protected function tearDown() {
		$this->cleanTempDir();
	}

	/**
	 * Completely remove the temp dir and its content if it exists.
	 */
	private function cleanTempDir() {
		if ( ! is_dir( $this->tempDir ) ) {
			return;
		}

		foreach ( glob( $this->tempDir . '/*' ) as $file ) {
			unlink( $file );
		}

		rmdir( $this->tempDir );
	}

	private function addComposerPlugin( PluginInterface $plugin ) {
		$pluginManagerReflection = new \ReflectionClass( $this->composer->getPluginManager() );

		$addPluginReflection = $pluginManagerReflection->getMethod( 'addPlugin' );
		$addPluginReflection->setAccessible( true );
		$addPluginReflection->invoke( $this->composer->getPluginManager(), $plugin );
	}

	/**
	 * @return UpdateOperation
	 */
	private function getUpdateOperation() {
		$initialPackage = new Package( 'wpackagist-plugin/codestyling-localization', '1.99', 'v1.99' );
		$initialPackage->setType( 'wordpress-plugin' );
		$initialPackage->setSourceUrl( 'https://plugins.svn.wordpress.org/codestyling-localization/' );

		$targetPackage = new Package( 'wpackagist-plugin/codestyling-localization', '1.99.99', 'v1.99.9' );
		$targetPackage->setType( 'wordpress-plugin' );
		$targetPackage->setSourceUrl( 'https://plugins.svn.wordpress.org/codestyling-localization/' );

		return new UpdateOperation( $initialPackage, $targetPackage );
	}

	public function test_it_is_registered_and_activated() {
		$plugin = new PluginAvailabilityPlugin();

		$this->addComposerPlugin( $plugin );
		$this->assertContains( $plugin, $this->composer->getPluginManager()->getPlugins() );
	}

	public function test_it_receives_event() {
		$this->addComposerPlugin( new PluginAvailabilityPlugin() );
		$operation = $this->getUpdateOperation();

		$this->composer->getEventDispatcher()->dispatchPackageEvent(
			PackageEvents::PRE_PACKAGE_UPDATE,
			false,
			new CompositeRepository( [] ),
			[ $operation ],
			$operation
		);

		$this->composer->getEventDispatcher()->dispatchScript( ScriptEvents::POST_UPDATE_CMD );

		$expectedOutput = <<<OUTPUT
<warning>The plugin wpackagist-plugin/codestyling-localization has not been updated in over two years. Please double-check before using it.</warning>
<warning>The plugin wpackagist-plugin/codestyling-localization does not seem to be available in the WordPress Plugin Directory anymore.</warning>

OUTPUT;
		$this->assertSame( $expectedOutput, $this->io->getOutput() );
	}
}

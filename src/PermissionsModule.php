<?php
declare(strict_types = 1);

namespace cusodede\permissions;

use cusodede\permissions\models\Permissions;
use cusodede\permissions\models\PermissionsCollections;
use cusodede\permissions\traits\UsersPermissionsTrait;
use pozitronik\helpers\ArrayHelper;
use pozitronik\helpers\ControllerHelper;
use pozitronik\traits\traits\ModuleTrait;
use ReflectionException;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\base\UnknownClassException;
use yii\console\Application as ConsoleApplication;
use yii\db\ActiveRecordInterface;
use yii\web\Controller;
use yii\web\IdentityInterface;

/**
 * Class PermissionsModule
 */
class PermissionsModule extends Module {
	use ModuleTrait;

	public $controllerPath = '@vendor/cusodede/yii2-permissions/src/controllers';

	private static ?string $_userIdentityClass = null;

	public const VERBS = [
		'GET' => 'GET',
		'HEAD' => 'HEAD',
		'POST' => 'POST',
		'PUT' => 'PUT',
		'PATCH' => 'PATCH',
		'DELETE' => 'DELETE'
	];

	/**
	 * @inheritDoc
	 */
	public function init():void {
		if (Yii::$app instanceof ConsoleApplication) {
			$this->controllerNamespace = 'cusodede\permissions\commands';
			$this->setControllerPath('@vendor/cusodede/yii2-permissions/src/commands');
		}
		parent::init();
	}

	/**
	 * @return string|ActiveRecordInterface
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	public static function UserIdentityClass():string|ActiveRecordInterface {
		if (null === static::$_userIdentityClass) {
			$identity = static::param('userIdentityClass', Yii::$app->user->identityClass);
			static::$_userIdentityClass = (is_callable($identity))
				?$identity()
				:$identity;
		}
		return static::$_userIdentityClass;
	}

	/**
	 * @return null|IdentityInterface|UsersPermissionsTrait
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @noinspection PhpDocSignatureInspection
	 */
	public static function UserCurrentIdentity():?IdentityInterface {
		$identity = static::param('userCurrentIdentity', Yii::$app->user->identity);
		return (is_callable($identity))
			?$identity()
			:$identity;
	}

	/**
	 * @param mixed $id
	 * @return IdentityInterface|null|UsersPermissionsTrait
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @noinspection PhpDocSignatureInspection
	 */
	public static function FindIdentityById(mixed $id):?IdentityInterface {
		return (null === $id)
			?static::UserCurrentIdentity()
			:static::UserIdentityClass()::findOne($id);
	}

	/**
	 * Возвращает список контроллеров в указанном каталоге, обрабатываемых модулем (в формате конфига)
	 * @return string[]
	 * @throws Throwable
	 */
	public static function GetControllersList(array $controllerDirs = ['@app/controllers']):array {
		$result = [];
		foreach ($controllerDirs as $controllerDir => $moduleId) {
			/*Если модуль указан в формате @moduleId, модуль не загружается, идентификатор подставится напрямую*/
			if (null !== $moduleId && '@' === $moduleId[0]) {
				$foundControllers = ControllerHelper::GetControllersList(Yii::getAlias($controllerDir), null, [Controller::class]);
				$module = substr($moduleId, 1);
			} else {
				$foundControllers = ControllerHelper::GetControllersList(Yii::getAlias($controllerDir), $moduleId, [Controller::class]);
				$module = null;
			}
			$result[$controllerDir] = ArrayHelper::map($foundControllers, static function(Controller $model) use ($module) {
				return (null === $module)?$model->id:$module.'/'.$model->id;
			}, static function(Controller $model) use ($module) {
				return (null === $module)?$model->id:$module.'/'.$model->id;
			});
		}
		return $result;
	}

	/**
	 * @param callable|null $initHandler
	 * @return void
	 * @throws Throwable
	 */
	public static function InitConfigPermissions(?callable $initHandler = null):void {
		$configPermissions = Permissions::GetConfigurationPermissions();
		foreach ($configPermissions as $permission) {
			$saved = $permission->save();
			if (null !== $initHandler) {
				$initHandler($permission, $saved);
			}
		}
	}

	/**
	 * @param string $path Путь к каталогу с контроллерами (рекурсивный корень).
	 * @param string|null $moduleId Модуль, которому принадлежат контроллеры (null для контроллеров приложения)
	 * @param callable|null $initPermissionHandler
	 * @param callable|null $initPermissionCollectionHandler
	 * @return void
	 * @throws InvalidConfigException
	 * @throws ReflectionException
	 * @throws Throwable
	 * @throws UnknownClassException
	 */
	public static function InitControllersPermissions(string $path = "@app/controllers", ?string $moduleId = null, ?callable $initPermissionHandler = null, ?callable $initPermissionCollectionHandler = null):void {
		$module = null;
		if ('' === $moduleId) $moduleId = null;//для совместимости со старым вариантом конфига
		/*Если модуль указан в формате @moduleId, модуль не загружается, идентификатор подставится напрямую*/
		if (null !== $moduleId && '@' === $moduleId[0]) {
			$foundControllers = ControllerHelper::GetControllersList(Yii::getAlias($path), null, [Controller::class]);
			$module = substr($moduleId, 1);
		} else {
			$foundControllers = ControllerHelper::GetControllersList(Yii::getAlias($path), $moduleId, [Controller::class]);
		}

		/** @var Controller[] $foundControllers */
		foreach ($foundControllers as $controller) {
			$module = $module??(($controller?->module?->id === Yii::$app->id)
					?null/*для приложения не сохраняем модуль, для удобства*/
					:$controller?->module?->id);
			$controllerActions = ControllerHelper::GetControllerActions(get_class($controller));
			$controllerPermissions = [];
			foreach ($controllerActions as $action) {
				$permission = new Permissions([
					'name' => sprintf("%s%s:%s", null === $module?"":"{$module}:", $controller->id, $action),
					'module' => $module,
					'controller' => $controller->id,
					'action' => $action,
					'comment' => "Разрешить доступ к действию {$action} контроллера {$controller->id}".(null === $module?"":" модуля {$module}")
				]);
				$saved = $permission->save();
				if (null !== $initPermissionHandler) {
					$initPermissionHandler($permission, $saved);
				}
				$controllerPermissions[] = $permission;
			}
			$controllerPermissionsCollection = new PermissionsCollections([
				'name' => sprintf("Доступ к контроллеру %s%s", null === $module?'':"{$module}:", $controller->id),
				'comment' => sprintf("Доступ ко всем действиям контроллера %s%s", $controller->id, null === $module?'':" модуля {$module}"),
			]);
			$controllerPermissionsCollection->relatedPermissions = $controllerPermissions;
			if (null !== $initPermissionCollectionHandler) {
				$initPermissionCollectionHandler($controllerPermissionsCollection, $controllerPermissionsCollection->save());
			}
		}
	}
}

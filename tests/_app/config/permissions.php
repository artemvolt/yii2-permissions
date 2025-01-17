<?php
declare(strict_types = 1);

use cusodede\permissions\PermissionsModule;

return [
	'class' => PermissionsModule::class,
	'params' => [
		'viewPath' => [
			'permissions' => './src/views/permissions',
			'permissions-collections' => './src/views/permissions-collections'
		],
		'controllerDirs' => [
			'@app/controllers' => null,
			'./src/controllers' => 'permissions',
			'@app/modules/test/controllers' => '@api'
		],
		'grantAll' => [],
		'grant' => [
			1 => ['choke_with_force']
		],
		'permissions' => [
			'choke_with_force' => [
				'comment' => 'Разрешение душить силой'
			],
			'execute_order_66' => [
				'comment' => 'Разрешение душить силой'
			]
		]
	]
];
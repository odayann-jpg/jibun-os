<?php
	
	// お客様用（非ログイン）
	Router::connect('/customer/',				array('controller' => 'members',	'action' => 'customer_search'));
	Router::connect('/customer/:uniquekey/',	array('controller' => 'members',	'action' => 'customer'),array('uniquekey' => '[a-zA-Z0-9]{32}'));
	
	// ログイン／ログアウト
	Router::connect('/login/',					array('controller' => 'pages',		'action' => 'login'));
	Router::connect('/logout/',					array('controller' => 'pages',		'action' => 'logout'));
	
	// ダッシュボード
	Router::connect('/',						array('controller' => 'pages',		'action' => 'index'));
	
	// 新規登録（編集へ）
	Router::connect('/:controller/add/', 		array('action' => 'edit'));
	
	CakePlugin::routes();
	require CAKE . 'Config' . DS . 'routes.php';
	
?>
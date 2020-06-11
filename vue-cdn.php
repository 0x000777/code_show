<?
$db1 = $GLOBALS['db1'];

if( CAJAX::isAjax() ) {

	// получить заказы
	if( isset($_POST['op']) && $_POST['op'] == 'getOrder' ) {
		$PARAMS = array(
			'ORDER_BY' => 'so.ID_ITEM DESC',
			'START' => (int)$_POST['START'],
			'LIMIT' => 50,
		);

		if( isset($_POST['SEARCH']) && trim($_POST['SEARCH']) != '' ) {
			if( mb_strlen($_POST['SEARCH']) <= 5 ) {
				// поиск по ИД заказа
				$PARAMS['ID_ITEM'] = (int)$_POST['SEARCH'];
			} else {
				// поиск по телефону
				$PARAMS['PHONE_USER'] = $_POST['SEARCH'];
			}
		}
		if( isset($_POST['DATEFROM']) && (int)$_POST['DATEFROM'] > 0 ) $PARAMS['DATE_CREATE_FROM'] = strtotime($_POST['DATEFROM']);
		if( isset($_POST['DATETO']) && (int)$_POST['DATETO'] > 0 ) $PARAMS['DATE_CREATE_TO'] = strtotime($_POST['DATETO']);
		if( isset($_POST['STATUS']) && (int)$_POST['STATUS'] > 0 ) $PARAMS['STATUS'] = (int)$_POST['STATUS'];
		if( isset($_POST['CITY']) && (int)$_POST['CITY'] > 0 ) $PARAMS['ID_CITY'] = (int)$_POST['CITY'];
		if( isset($_POST['SORT']) && (int)$_POST['SORT'] > 0 ) {
			if( (int)$_POST['SORT'] == 1 ) $PARAMS['ORDER_BY'] = "so.ID_ITEM DESC";			// Номер заказа
			if( (int)$_POST['SORT'] == 2 ) $PARAMS['ORDER_BY'] = "so.DATE_ARRIVED DESC";	// Дата прибытия
			if( (int)$_POST['SORT'] == 3 ) $PARAMS['ORDER_BY'] = "so.DATE_DONE DESC";		// Дата выдачи
		}
		if( isset($_POST['COUNT']) && (int)$_POST['COUNT'] > 0 ) $PARAMS['LIMIT'] = (int)$_POST['COUNT'];

		$ORDERS = MADMINSTOREORDER::getOrders( $PARAMS );

		$DATA_ARR = array();
		foreach( $ORDERS as $key => $val ) {
			// информация о точке
			$POINT_INFO = MPLACE::getRetailsByID( $val['ID_POINT'] );

			$tmp = array(
				'id' => (int)$val['ID_ITEM'],
				'idCity' => (int)$POINT_INFO['ID_PLACE'],
				'idPoint' => (int)$POINT_INFO['ID_ITEM'],
				'status' => (int)$val['STATUS'],
				'phone' => $val['PHONE'],
				'ip' => $val['IP_ADDRESS'],
				'city' => $POINT_INFO['CITY_NAME'],
				'region' => $POINT_INFO['CITY_REGION'],
				'adress' => $POINT_INFO['ADDRESS'],
				'totalPrice' => (int)$val['TOTAL_PRICE'],
				'goods' => array(),
			);

			// даты
			if( (int)$val['DATE_CREATE'] > 0 ) {
				$tmp['create'] = array(
					'date' => date('d.m.Y', $val['DATE_CREATE']),
					'time' => date('H:i', $val['DATE_CREATE']),
				);
			}
			if( (int)$val['DATE_ARRIVED'] > 0 ) {
				$tmp['arrived'] = array(
					'date' => date('d.m.Y', $val['DATE_ARRIVED']),
					'time' => date('H:i', $val['DATE_ARRIVED']),
				);
			}
			if( (int)$val['DATE_DONE'] > 0 ) {
				$tmp['done'] = array(
					'date' => date('d.m.Y', $val['DATE_DONE']),
					'time' => date('H:i', $val['DATE_DONE']),
				);
			}

			// информация о товарах
			$GOODS_LIST = MSTOREORDER::getOrderElementsByIDOrder( $val['ID_ITEM'] );
			foreach( $GOODS_LIST as $GOODS_key => $GOODS_val ) {
				// получаем информацию о товаре
				$GOODS = MADMINSTORE::getElementByID( $GOODS_val['ID_ELEMENT'] );

				// получаем картинку
				$IMG = CIMAGE::getImage(
					$GOODS['PREVIEW_PICTURE'],
					array(
						'width' => 64,
						'height' => 64,
						'type' => 1,
						'quality' => 90,
						'to_jpg' => 1,
					),
					'adm_store_1'
				)['resize_src'];


				$tmp['goods'][] = array(
					'id' => (int)$GOODS['ID_ITEM'],
					'img' => $IMG,
					'quantity' => (int)$GOODS_val['QUANTITY'],
					'name' => $GOODS['NAME'],
				);
			}

			// информация о пользователе
			$USER_INFO = CUSER::getByID( $val['ID_USER'] );

			$tmp['age'] = '';
			if( $USER_INFO['AGE'] == 0 ) $tmp['age'] = '4 - 10 лет';
			if( $USER_INFO['AGE'] == 1 ) $tmp['age'] = '11 - 14 лет';
			if( $USER_INFO['AGE'] == 2 ) $tmp['age'] = '15 - 18 лет';
			if( $USER_INFO['AGE'] == 3 ) $tmp['age'] = '19 - 25 лет';
			if( $USER_INFO['AGE'] == 4 ) $tmp['age'] = '26+ лет';


			$DATA_ARR[] = $tmp;
		}

		if( !isset($DATA_ARR[0]) ) header("appendPage: end");
		CAJAX::sendJSON( ['response' => $DATA_ARR] );
	}

	// изменить статус у заказа
	if( isset($_POST['op']) && $_POST['op'] == 'changeStatus' ) {
		if( !isset($_POST['ID_ORDER']) || (int)$_POST['ID_ORDER'] <= 0 ) CAJAX::sendMSG( 'Не передан ИД заказа' );
		if( !isset($_POST['STATUS']) || (int)$_POST['STATUS'] < 0 ) CAJAX::sendMSG( 'Не передан статус' );

		// Обрабатывается
		if( (int)$_POST['STATUS'] == 0 ) {
			$PARAMS['DATE_ARRIVED'] = 0;
			$PARAMS['DATE_DONE'] = 0;
		}

		// Получен
		if( (int)$_POST['STATUS'] == 1 ) {
			$PARAMS['DATE_DONE'] = CTIME::$N_TIME;
		}

		// В пути
		if( (int)$_POST['STATUS'] == 2 ) {
			$PARAMS['DATE_ARRIVED'] = 0;
			$PARAMS['DATE_DONE'] = 0;
		}

		// Ждёт получения
		if( (int)$_POST['STATUS'] == 3 ) {
			$PARAMS['DATE_ARRIVED'] = CTIME::$N_TIME;

			// отпрвить смс о доставке заказа
			// получаем информацию о заказе
			$ORDER_INFO = MADMINSTOREORDER::getOrderByID( $_POST['ID_ORDER'] );

			// получаем информацию о пользователе
			$USER_INFO = CUSER::getByID( $ORDER_INFO['ID_USER'] );

			// отправляем смс напоминание о том что заказ пришел и его нужно забрать
			CPHONE::sendSMS( $USER_INFO['LOGIN'], "Ваш заказ №".$ORDER_INFO['ID_ITEM']." готов к получению по адресу: ".$ORDER_INFO['ADDRESS'], 0, 0 );
		}

		// Отменён
		if( (int)$_POST['STATUS'] == 4 ) {
			$PARAMS['DATE_ARRIVED'] = 0;
			$PARAMS['DATE_DONE'] = 0;

			// получаем информацию о заказе
			$ORDER_INFO = MADMINSTOREORDER::getOrderByID( $_POST['ID_ORDER'] );


			$db1->query( "START TRANSACTION" );

				// возвращаем на баланс пользователя сумму заказа
				$db1->query("
					UPDATE
						`user`
					SET
						`BALANCE` = BALANCE + ".$db1->safeInt( $ORDER_INFO['TOTAL_PRICE'] )."
					WHERE
						`ID_USER` = ".$db1->safeInt( $ORDER_INFO['ID_USER'] )."
				");
				if( $db1->affectedRows() <= 0 ) {
					$db1->query( "ROLLBACK" );
					CAJAX::sendMSG( 'Ошибка' );
				}

				// заносим в выписку
				$HISTORY_PARAMS = array(
                    'ID_ELEMENT' => $_POST['ID_ORDER'],
                    'STATUS'     => 3,
                    'PRICE'      => $ORDER_INFO['TOTAL_PRICE'],
                    'ID_USER'    => $ORDER_INFO['ID_USER'],
				);
				CUSER::insertHistory( $HISTORY_PARAMS );
				if( $db1->error()['code'] != 0 ) {
					$db1->query( "ROLLBACK" );
					CAJAX::sendMSG( 'Ошибка' );
				}

			$db1->query( "COMMIT" );
		}

		$PARAMS['STATUS'] = $_POST['STATUS'];
		$ORDERS = MADMINSTOREORDER::updateOrderDB( $_POST['ID_ORDER'], $PARAMS );


		$DATA_ARR = array( 'status' => 'ok' );
		CAJAX::sendJSON( ['response' => $DATA_ARR] );
	}

	// изменить пункт выдачи у заказа
	if( isset($_POST['op']) && $_POST['op'] == 'changePoint' ) {
		if( !isset($_POST['ID_ORDER']) || (int)$_POST['ID_ORDER'] <= 0 ) CAJAX::sendMSG( 'Не передан ИД заказа' );
		if( !isset($_POST['ID_POINT']) || (int)$_POST['ID_POINT'] <= 0 ) CAJAX::sendMSG( 'Не передан ИД точки' );

		// получить информацию по точке
		$POINT_INFO = MADMINPLACE::getRetailsByID( $_POST['ID_POINT'] );


		$PARAMS = array(
			'ID_CITY' => $POINT_INFO['ID_PLACE'],
			'ADDRESS' => $POINT_INFO['ADDRESS'],
			'ID_POINT' => $_POST['ID_POINT'],
		);
		$ORDERS = MADMINSTOREORDER::updateOrderDB( $_POST['ID_ORDER'], $PARAMS );


		$DATA_ARR = array( 'status' => 'ok' );
		CAJAX::sendJSON( ['response' => $DATA_ARR] );
	}

	// отправить смс напоминание пользователю, что нужно забрать заказ
	if( isset($_POST['op']) && $_POST['op'] == 'resendSms' ) {
		if( !isset($_POST['ID_ORDER']) || (int)$_POST['ID_ORDER'] <= 0 ) CAJAX::sendMSG( 'Не передан ИД заказа' );

		// получаем информацию о заказе
		$ORDER_INFO = MADMINSTOREORDER::getOrderByID( $_POST['ID_ORDER'] );

		// получаем информацию о пользователе
		$USER_INFO = CUSER::getByID( $ORDER_INFO['ID_USER'] );

		// отправляем смс напоминание о том что заказ пришел и его нужно забрать
		CPHONE::sendSMS( $USER_INFO['LOGIN'], "Ваш заказ №".$ORDER_INFO['ID_ITEM']." готов к получению по адресу: ".$ORDER_INFO['ADDRESS'], 0, 0 );


		$DATA_ARR = array( 'status' => 'ok' );
		CAJAX::sendJSON( ['response' => $DATA_ARR] );
	}

}

// получаем города
$CITY_LIST = MPLACE::getCityWithRetails();
$CITY_JSON = array();
foreach( $CITY_LIST as $CITY_key => $CITY_val ) {
	// получаем информацию о точках
	$POINTS_LIST = MPLACE::getRetailsByIDCity( $CITY_val['ID_ITEM'] );

	$POINT_JSON = array();
	foreach( $POINTS_LIST as $POINTS_key => $POINTS_val ) {
		$POINT_JSON[] = array(
			'id' => (int)$POINTS_val['ID_ITEM'],
			'address' => $POINTS_val['ADDRESS'],
			'geoLat' => (float)$POINTS_val['GEO_LAT'],
			'geoLon' => (float)$POINTS_val['GEO_LON'],
			'time' => $POINTS_val['TIME'],
		);
	}

	$CITY_JSON[] = array(
		'id' => (int)$CITY_val['ID_ITEM'],
		'name' => $CITY_val['NAME'],
		'region' => $CITY_val['REGION'],
		'points' => $POINT_JSON,
	);
}
$CITY_JSON = json_encode( $CITY_JSON );
?>
<? include ROOT.'/views/layouts/admin_header.php'; ?>



<style>
<?CSCSS::start()?>
#app {
	[v-cloak] {
		display: none;
	}
}

.tr-hidden {
	color: #AEAEB2;
}

.goodsOrdered {
	line-height: 0;

	img {
		width: 36px; height: 36px; border-radius: 50%; margin-left: -12px; box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.05);
	}
	img:nth-child(1) {
		margin-left: 0;
	}
}

.dropdown {
	&-overlay {
		position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1; display: flex;
	}
	&-modal {
		background-color: #fff; box-shadow: 0px 15px 30px rgba(0, 0, 0, 0.2); border-radius: 4px; padding: 8px 0px; position: absolute; top: 5px; right: 0; z-index: 2;

		.dropdown-menu {
			line-height: 16px; text-align: left;

			li {
				white-space: nowrap; padding: 9px 16px; cursor: pointer;

				&:hover {
					background-color: #F9F9F9;
				}
			}
		}
	}

	.btn-operation {
		background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xMiA3YzEuMzc1IDAgMi41LTEuMTI1IDIuNS0yLjVTMTMuMzc1IDIgMTIgMmEyLjUwNyAyLjUwNyAwIDAwLTIuNSAyLjVDOS41IDUuODc1IDEwLjYyNSA3IDEyIDd6bTAgMi41QTIuNTA3IDIuNTA3IDAgMDA5LjUgMTJjMCAxLjM3NSAxLjEyNSAyLjUgMi41IDIuNXMyLjUtMS4xMjUgMi41LTIuNS0xLjEyNS0yLjUtMi41LTIuNXptMCA3LjVhMi41MDcgMi41MDcgMCAwMC0yLjUgMi41YzAgMS4zNzUgMS4xMjUgMi41IDIuNSAyLjVzMi41LTEuMTI1IDIuNS0yLjVTMTMuMzc1IDE3IDEyIDE3eiIgZmlsbD0iI0FFQUVCMiIvPjwvc3ZnPg==') no-repeat center center; width: 24px; height: 24px;
	}

	.fade {
		&-enter-to {
			animation-duration: 0.3s; z-index: 9999;
		}
		&-leave-to {
			animation-duration: 0.15s;
		}
	}
}
.popup {
	.modal-w {
		background-color: rgba(0, 0, 0, 0.44); position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1000; display: flex;
	}
	.modal-block {
		background-color: #F9F9F9; border-radius: 6px; margin: auto; position: relative; padding: 18px; box-sizing: border-box; min-width: 300px; max-width: 90vw; max-height: 90vh; overflow-y: auto; animation-duration: 0.25s;
	}

	.fade {
		&-enter-to {
			animation-duration: 0.4s;
		}
		&-leave-to {
			animation-duration: 0.3s;
		}
	}
}
.btn-close {
	background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTE0LjE1IDEybDcuNDM4LTcuNTYxYTEuNDM1IDEuNDM1IDAgMDAwLTIuMDEyIDEuNDI2IDEuNDI2IDAgMDAtMi4wMzQgMEwxMC4xMzQgMTJsOS40MiA5LjU3NGMuNTYuNTY4IDEuNDc1LjU2OCAyLjAzNSAwYTEuNDM1IDEuNDM1IDAgMDAwLTIuMDEzTDE0LjE1IDEyeiIgZmlsbD0iI0ZGNDQ2OCIvPjxwYXRoIGQ9Ik05Ljg1IDEyTDIuNDExIDQuNDM5YTEuNDM1IDEuNDM1IDAgMDEwLTIuMDEyIDEuNDI2IDEuNDI2IDAgMDEyLjAzNCAwTDEzLjg2NiAxMmwtOS40MiA5LjU3NGExLjQyNiAxLjQyNiAwIDAxLTIuMDM0IDAgMS40MzUgMS40MzUgMCAwMTAtMi4wMTNMOS44NDkgMTJ6IiBmaWxsPSIjRkY0NDY4Ii8+PC9zdmc+') no-repeat center center; width: 24px; height: 24px; cursor: pointer;
}
<?=CSCSS::end()?>
</style>



<div class='content' style='font-size: 14px;'>
	<div style='margin: 12px 0 24px 0;'>
		<?=CNAVIGATION::get()?>
	</div>
	<div class='' style='' id='app'>
		<div style=''>
			<form autocomplete='off' class='pure-form'>
				<div class='pure-g pure-gutter' style=''>
					<div class='pure-u-3-12' style=''>
						<div class='' style=''>
							<input type='text' name='search' class='' style='width: 100%;' placeholder='Номер заказа или телефон без 8' v-model="filters.search">
						</div>
					</div>
					<div class='pure-u-3-12' style=''>
						<div class='pure-g pure-gutter' style=''>
							<div class='pure-u-1-2' style=''>
								<input type='text' name='dateFrom' class='date_picker' style='width: 100%;' placeholder='с' v-model="filters.dateFrom">
							</div>
							<div class='pure-u-1-2' style=''>
								<input type='text' name='dateTo' class='date_picker' style='width: 100%;' placeholder='до' v-model="filters.dateTo">
							</div>
						</div>
					</div>
					<div class='pure-u-3-12' style=''>
						<div class='' style=''>
							<select name='city' style='width: 100%;' v-model="filters.city">
								<option value='' selected>Все города</option>
								<option :value='item.id' v-for="(item, item_k) in cityList" :key="item.id">{{item.name}} ({{item.region}})</option>
							</select>
						</div>
					</div>
					<div class='pure-u-3-12' style=''>
						<div class='pure-g pure-gutter' style=''>
							<div class='pure-u-1-2' style=''>
								<select name='status' style='width: 100%;' v-model="filters.status">
									<option value='' selected>Все статусы</option>
									<option value='0'>Новый</option>
									<option value='1'>Получен</option>
									<option value='2'>В пути</option>
									<option value='3'>Ждёт получения</option>
									<option value='4'>Отменён</option>
								</select>
							</div>
							<div class='pure-u-1-2' style=''>
								<select name='sort' style='width: 100%;' v-model="filters.sort">
									<option value='1' selected>Номер заказа</option>
									<option value='2'>Дата прибытия</option>
									<option value='3'>Дата выдачи</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class='' style='margin-top: 16px; text-align: right;'>
					<a href='javascript:void(0)' class='' style='font-size: 13px; border-bottom: 1px solid #007AFF; color: #007AFF;' @click="clearFilter">Сбросить фильтры</a>
				</div>
			</form>
		</div>
		<div class='' style='margin-top: 24px;'>
			<table class='pure-table pure-table-striped pure-table-horizontal' style='width: 100%;'>
				<thead>
					<tr>
						<th width='40'>#</th>
						<th width='120'>Статус</th>
						<th width='100'>Пользователь</th>
						<th width='240'>Пункт выдачи</th>
						<th width='75'>Создан</th>
						<th width='75'>Прибыл</th>
						<th width='75'>Выдан</th>
						<th width=''>Подарки</th>
						<th width='20'></th>
					</tr>
				</thead>
				<tbody v-cloak>
					<tr :class="{'tr-hidden' : order.status == 1}" v-for="(order, order_k) in orders" :key="order.id">
						<td>{{order.id}}</td>
						<td>
							<div class='' style='color: #FFA717;' v-if="order.status == 0">Новый</div>
							<div class='' style='color: #AEAEB2;' v-if="order.status == 1">Получен</div>
							<div class='' style='' v-if="order.status == 2">В пути</div>
							<div class='' style='' v-if="order.status == 3"><span class='textBg'>Ждёт получения</span></div>
							<div class='' style='color: #FF4468;' v-if="order.status == 4">Отменён</div>
						</td>
						<td>
							<div class='' style='line-height: 130%;'>
								<div class='' style=''>{{order.phone}}</div>
								<div class='' style='font-size: 13px; color: #AEAEB2;'>{{order.ip}}</div>
							</div>
						</td>
						<td>
							<div class='' style='line-height: 130%;'>
								<div class='' style=''>{{order.city}} ({{order.region}})</div>
								<div class='' style='font-size: 13px; color: #AEAEB2;'>{{order.adress}}</div>
							</div>
						</td>
						<td>
							<div class='' style='line-height: 130%;' v-if="order.create != null">
								<div class='' style=''>{{order.create.date}}</div>
								<div class='' style='font-size: 13px; color: #AEAEB2;'>{{order.create.time}}</div>
							</div>
							<div class='' style='color: #AEAEB2;' v-else>—</div>
						</td>
						<td>
							<div class='' style='line-height: 130%;' v-if="order.arrived != null">
								<div class='' style=''>{{order.arrived.date}}</div>
								<div class='' style='font-size: 13px; color: #AEAEB2;'>{{order.arrived.time}}</div>
							</div>
							<div class='' style='color: #AEAEB2;' v-else>—</div>
						</td>
						<td>
							<div class='' style='line-height: 130%;' v-if="order.done != null">
								<div class='' style=''>{{order.done.date}}</div>
								<div class='' style='font-size: 13px; color: #AEAEB2;'>{{order.done.time}}</div>
							</div>
							<div class='' style='color: #AEAEB2;' v-else>—</div>
						</td>
						<td>
							<div class='goodsOrdered' style=''>
								<img :src='img_item.img' style='' :title='img_item.name' v-for="(img_item, img_item_k) in order.goods" :key="img_item_k">
							</div>
						</td>
						<td style='text-align: right; line-height: 0px;'>
							<template v-if="order.status != 4">
								<template v-if="order.status == 3">
									<dropdown :list="[ { index: 0, name: 'Подробнее о заказе' }, { index: 1, name: 'Отправить напоминание' } ]" @onselect="function(e) { goodOptions(order_k, e) }"></dropdown>
								</template>
								<template v-else>
									<dropdown :list="[ { index: 0, name: 'Подробнее о заказе' } ]" @onselect="function(e) { goodOptions(order_k, e) }"></dropdown>
								</template>
							</template>
						</td>
					</tr>
				</tbody>
			</table>

			<popup :isopen="popUp.goodInfo.isopen == true && popUp.orderDelete.isopen == false && popUp.resendSms.idOrder == 0" @onisopen="function() { popUp.goodInfo.isopen = false; popUp.goodInfo.good = {}; }">
				<div class='' style='margin: 22px; width: 1038px;'>
					<form autocomplete='off' class='pure-form'>
						<div class='' style='position: relative;'>
							<span class='robotoBold' style='font-size: 32px;'>Заказ № {{popUp.goodInfo.good.id}}</span>
							<span class='' style='position: absolute; margin: 7px 0 0 16px;'>
								<transition name='fader' enter-active-class='animated fadeIn' leave-active-class='animated fadeOut' appear>
									<span style='color: #00B956;' v-if="popUp.goodInfo.statusInformer == true">Изменения сохранены</span>
								</transition>
							</span>
							<span style='position: absolute; right: 0;'>
								<img src='/images/0.png' class='btn-close' style='width: 24px; height: 24px;' @click="popUp.goodInfo.isopen = false; popUp.goodInfo.good = {};">
							</span>
						</div>
						<div class='' style=''>
							<div class='pure-g pure-gutter' style='margin-top: 17px;'>
								<div class='pure-u-1-2' style=''>
									<div class='pure-g pure-gutter' style=''>
										<div class='pure-u-3-9' style=''>
											<div class='' style='padding: 4px 0px;'>
												<label> <input type='radio' name="status" value='0' style='margin-right: 3px;' v-model="popUp.goodInfo.good.status"> <span style='color: #FFA717;'>Новый</span> </label>
											</div>
											<div class='' style='padding: 4px 0px;'>
												<label> <input type='radio' name="status" value='2' style='margin-right: 3px;' v-model="popUp.goodInfo.good.status"> В пути </label>
											</div>
										</div>
										<div class='pure-u-3-9' style=''>
											<div class='' style='padding: 4px 0px;'>
												<label> <input type='radio' name="status" value='3' style='margin-right: 3px;' v-model="popUp.goodInfo.good.status"> <span style='color: #5647FF;'>Ждёт получения</span> </label>
											</div>
											<div class='' style='padding: 4px 0px;'>
												<label> <input type='radio' name="status" value='1' style='margin-right: 3px;' v-model="popUp.goodInfo.good.status"> <span style='color: #AEAEB2;'>Получен</span> </label>
											</div>
										</div>
									</div>
								</div>
								<div class='pure-u-1-2' style=''>
									<div class='pure-g pure-gutter' style=''>
										<div class='pure-u-1-5' style=''>
											<div class='' style=''>Создан</div>
											<div class='' style=''>Прибыл</div>
											<div class='' style=''>Выдан</div>
										</div>
										<div class='pure-u-2-5' style=''>
											<div class='' style=''>
												<div class='' style='' v-if="popUp.goodInfo.good.create != undefined">{{popUp.goodInfo.good.create.date}} в {{popUp.goodInfo.good.create.time}}</div>
												<div class='' style='' v-else><span style='color: #AEAEB2;'>—</span></div>
											</div>
											<div class='' style=''>
												<div class='' style='' v-if="popUp.goodInfo.good.arrived != undefined">{{popUp.goodInfo.good.arrived.date}} в {{popUp.goodInfo.good.arrived.time}}</div>
												<div class='' style='' v-else><span style='color: #AEAEB2;'>—</span></div>
											</div>
											<div class='' style=''>
												<div class='' style='' v-if="popUp.goodInfo.good.done != undefined">{{popUp.goodInfo.good.done.date}} в {{popUp.goodInfo.good.done.time}}</div>
												<div class='' style='' v-else><span style='color: #AEAEB2;'>—</span></div>
											</div>
										</div>
										<div class='pure-u-1-5' style=''>
											<div class='' style=''>Подарков</div>
											<div class='' style=''>Стоимость</div>
										</div>
										<div class='pure-u-1-5' style=''>
											<div class='' style='' v-if="popUp.goodInfo.good.goods != undefined">{{popUpGoodInfoCount}} шт</div>
											<div class='' style=''>{{popUp.goodInfo.good.totalPrice}} XP</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div style='margin: 32px 0px; height: 1px; font-size: 1px; line-height: 1px; background-color: #EBEBEB;'>&nbsp;</div>
						<div class='' style=''>
							<div style='display: flex; justify-content: flex-start; align-content: space-between; flex-wrap: wrap;'>
								<div style='width: 250px; height: 50px;' v-for="(good, good_k) in popUp.goodInfo.good.goods" :key="good.id">
									<div style='display: table; width: 100%;'>
										<div style='display: table-cell; width: 64px; vertical-align: top;'>
											<img :src="good.img" style='width: 48px; height: 48px; border-radius: 50%;'>
										</div>
										<div style='display: table-cell; width: auto; vertical-align: top;'>
											<div class='' style='font-size: 13px; line-height: 14px;'>{{good.name}}</div>
											<div class='' style='font-size: 13px; color: #AEAEB2;'>{{good.quantity}} шт.</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div style='margin: 32px 0px; height: 1px; font-size: 1px; line-height: 1px; background-color: #EBEBEB;'>&nbsp;</div>
						<div class='' style=''>
							<div class='robotoBold' style='font-size: 24px;'>Заказчик</div>
							<div class='' style='margin-top: 16px;'>
								<span style=''>{{popUp.goodInfo.good.phone}}</span>
								<span style='margin-left: 16px;'>{{popUp.goodInfo.good.ip}}</span>
								<span style='margin-left: 16px;'>{{popUp.goodInfo.good.age}}</span>
							</div>
						</div>
						<div style='margin: 32px 0px; height: 1px; font-size: 1px; line-height: 1px; background-color: #EBEBEB;'>&nbsp;</div>
						<div class='' style=''>
							<div class='robotoBold' style='font-size: 24px;'>Пункт выдачи</div>
							<div class='pure-g-auto' style='margin-top: 16px;'>
								<div class='pure-u-auto' style=''>
									<select name='city' style='width: 300px;' v-model="popUp.goodInfo.good.idCity" @change="popUp.goodInfo.changePoint = true">
										<option :value="cityItem.id" v-for="(cityItem, cityItem_k) in cityList" :key="cityItem.id" :selected="(cityItem.id == popUp.goodInfo.good.idCity)">{{cityItem.name}}</option>
									</select>
								</div>
								<div class='pure-u-auto' style=''>
									<select name='point' style='width: 300px;' @change="popUp.goodInfo.changePoint = true" ref="point">
										<option :value="point.id" v-for="(point, point_k) in pointOfCity.points" :key="point.id" :selected="(point.id == popUp.goodInfo.good.idPoint)">{{point.address}}</option>
									</select>
								</div>
								<div class='pure-u-auto pure-u-vertical-center' v-if="popUp.goodInfo.changePoint == true">
									<a href='javascript:void(0)' class='pure-button pure-button-info' style='width: 120px;' @click="changePoint">Сохранить</a>
								</div>
							</div>
						</div>

						<div class='' style='margin-top: 16px;'>
							<a href='javascript:void(0)' class='pure-button pure-button-success' style='margin-right: 16px;' v-if="popUp.goodInfo.good.status == 3" @click="popUp.resendSms.idOrder = popUp.goodInfo.good.id">Отправить напоминание</a>
							<a href='javascript:void(0)' class='pure-button pure-button-danger' @click="popUp.orderDelete.isopen = true">Отменить заказ</a>
						</div>
					</form>
				</div>
			</popup>

			<popup :isopen="popUp.orderDelete.isopen == true" @onisopen="function() { popUp.orderDelete.isopen = false }">
				<div class='' style='margin: 22px 14px;'>
					<div class='' style='text-align: center;'><span class='textBg graphikBold' style='font-size: 22px; line-height: 115%;'>Удалить заказ?</span></div>
					<div class='' style='margin-top: 8px;'>Данный заказ будет нельзя восстановить!<br> XP заказа будут возвращены на баланс пользователя.</div>

					<div class='pure-g pure-gutter' style='margin-top: 32px;'>
						<div class='pure-u-1-2' style=''>
							<a href='javascript:void(0)' class='pure-button pure-button-success' style='width: 100%;' @click="cancelOrder">Удалить</a>
						</div>
						<div class='pure-u-1-2' style=''>
							<a href='javascript:void(0)' class='pure-button pure-button-danger' style='width: 100%;' @click="function() { popUp.orderDelete.isopen = false }">Отмена</a>
						</div>
					</div>
				</div>
			</popup>
			<popup :isopen="popUp.resendSms.idOrder > 0" @onisopen="function() { popUp.resendSms.idOrder = 0 }">
				<div class='' style='margin: 22px 14px;'>
					<div class='' style='text-align: center;'><span class='textBg graphikBold' style='font-size: 22px; line-height: 115%;'>Отправить напоминание?</span></div>
					<div class='' style='margin-top: 8px;'>Заказчик получит СМС-уведомление - напоминание,<br> что необходимо забрать заказ.</div>

					<div class='pure-g pure-gutter' style='margin-top: 32px;'>
						<div class='pure-u-1-2' style=''>
							<a href='javascript:void(0)' class='pure-button pure-button-success' style='width: 100%;' @click="resendSms">Отправить</a>
						</div>
						<div class='pure-u-1-2' style=''>
							<a href='javascript:void(0)' class='pure-button pure-button-danger' style='width: 100%;' @click="function() { popUp.resendSms.idOrder = 0 }">Отмена</a>
						</div>
					</div>
				</div>
			</popup>
		</div>
	</div>
</article>



<script>
<?CJSPACKER::start();?>
// DropDown меню
Vue.component('dropdown', {
	props: {
		list: Array,
	},
	data: function () {
		return {
			isopen: false,
		}
	},
	methods: {
		closeW: function() {
			this.isopen = false;
		},
		select: function(index) {
			let self = this;

			self.isopen = false;
			self.$emit("onselect", index);
		},
	},
	template: `
		<div class='dropdown'>
			<div class='' style='color: #222;'>
				<a href='javascript:void(0)' class='' style='' @click="isopen = true">
					<img src='/images/0.png' class='btn-operation'>
				</a>
				<transition name="fade" enter-active-class="animated fadeIn" leave-active-class="animated fadeOut">
					<div class='' style='position: relative;' v-if="isopen" v-cloak>
						<div class='dropdown-overlay' style='' @click="closeW"></div>
						<div class='dropdown-modal' style=''>
							<ul class='dropdown-menu' style=''>
								<li style='' v-for="(item, item_k) in list" :key="item_k" @click="select(item.index)">{{item.name}}</li>
							</ul>
						</div>
					</div>
				</transition>
			</div>
		</div>
	`,
});
// модальное окно
Vue.component('popup', {
	props: {
		isopen: Boolean,
	},
	data: function() {
		return {};
	},
	methods: {
		closeW: function() {
			this.$emit("onisopen", false);
		}
	},
	template: `
		<div class='popup'>
			<transition name="fade" enter-active-class="animated fadeIn" leave-active-class="animated fadeOut">
				<div class="modal-w" v-if="isopen" @click="closeW" v-cloak>
					<div class="modal-block animated zoomIn" @click.stop>
						<slot></slot>
					</div>
				</div>
			</transition>
		</div>
	`,
});



var app = new Vue({
	el: '#app',
	data: {
		filters: {
			search: '',
			dateFrom: '',
			dateTo: '',
			city: '',
			status: '',
			sort: 1,
		},
		filtersDef: {},
		ajax: {
			isLoading: false,
		},
		orders: [],
		popUp: {
			resendSms: {
				idOrder: 0,
			},
			goodInfo: {
				isopen: false,
				statusInformer: false,
				changePoint: false,
				good: {},
			},
			orderDelete: {
				isopen: false,
			},
		},
		cityList: <?=$CITY_JSON?>,
	},
	created: function() {
		let self = this;

		self.filtersDef = JSON.parse(JSON.stringify(self.filters));

		axios.defaults.headers = {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
		};

		self.getOrder('new');
	},
	mounted: function() {
		let self = this;

		Vue.nextTick(function () {
			$('.date_picker').datepicker({
				timepicker: false,
				autoClose: true,
				toggleSelected: false,
				dateFormat: 'dd.mm.yyyy',
				keyboardNav: false,
				onSelect(formattedDate, date, inst) {
					jQuery(inst.el)[0].dispatchEvent(new Event('input'));
				},
			}).data('datepicker');
			$('.date_picker').on('mouseenter', function() {
				var newDate = parseDate( $(this).val() );
				if( newDate == false ) return;
			}).on('dblclick', function() {
				$(this).val('');
			});


			// подзагрузка контента
			window.addEventListener('scroll', function() {
				var Ws = window.pageYOffset;
				var Sh = document.documentElement.clientHeight;
				var Wh = document.body.scrollHeight;

				var PagePercent = Wh-Sh-Ws;
				if( PagePercent < 300 && self.filters.isLoading == false ) {
					self.getOrder('append');
				}
			});
		});
	},
	computed: {
		// получает общее колличество заказанных товаров, в окне детальной информации о заказе
		popUpGoodInfoCount: function() {
			let self = this;
			let count = 0;

			for(let index = 0; index < self.popUp.goodInfo.good.goods.length; index++) {
				const item = self.popUp.goodInfo.good.goods[index];

				count += parseInt( item.quantity );
			}

			return count;
		},

		// отсортировать город для вывода точек
		pointOfCity: function() {
			let self = this;

			for(let index = 0; index < self.cityList.length; index++) {
				const city = self.cityList[index];
				if( city.id == self.popUp.goodInfo.good.idCity ) return city;
			}

			return [];
		},
	},
	methods: {
		// сбросить фильтры по-умолчанию
		clearFilter: function(type) {
			let self = this;

			self.filters = JSON.parse(JSON.stringify(self.filtersDef));
		},

		// получить заказы
		getOrder: function(type) {
			let self = this;
			let start = 0;
			let count = 0;

			if(type == 'new') {
				self.filters.isLoading = false;
			}
			if(type == 'append') {
				start = self.orders.length;
			}
			if(type == 'renew') {
				count = self.orders.length;
			}

			self.filters.isLoading = true;
			axios.post(window.location.pathname, querystring.stringify({
				op: 'getOrder',
				SEARCH: self.filters.search,
				DATEFROM: self.filters.dateFrom,
				DATETO: self.filters.dateTo,
				CITY: self.filters.city,
				STATUS: self.filters.status,
				SORT: self.filters.sort,
				START: start,
				COUNT: count,
			})).then(function ( res ) {
				if( res.data.response != undefined ) {
					if(type == 'new' || type == 'renew') {
						self.orders = res.data.response;
					}
					if(type == 'append' && res.data.response.length > 0) {
						for( let index = 0; index < res.data.response.length; index++ ) {
							const element = res.data.response[index];
							self.orders.push(element);
						}
					}
				}

				if( res.headers.appendpage == undefined ) self.filters.isLoading = false;
			});
		},

		// меню товара
		goodOptions: function(good_index, menu_index) {
			let self = this;

			// показать детальную о товаре
			if( menu_index == 0 ) {
				self.popUp.goodInfo.good = self.orders[good_index];
				self.popUp.goodInfo.isopen = true;
			}

			// попап отправить напоминание
			if( menu_index == 1 ) {
				self.popUp.resendSms.idOrder = self.orders[good_index].id;
			}
		},

		// изменить статус заказа
		changeStatus: function(status) {
			let self = this;

			axios.post(window.location.pathname, querystring.stringify({
				op: 'changeStatus',
				ID_ORDER: self.popUp.goodInfo.good.id,
				STATUS: status,
			})).then(function ( res ) {
				if( res.data.response != undefined ) {
					self.popUp.goodInfo.statusInformer = true;

					self.getOrder('renew');
				}
			});
		},

		// изменить пункт выдачи у заказа
		changePoint: function() {
			let self = this;

			axios.post(window.location.pathname, querystring.stringify({
				op: 'changePoint',
				ID_ORDER: self.popUp.goodInfo.good.id,
				ID_POINT: self.$refs.point.value,
			})).then(function ( res ) {
				if( res.data.response != undefined ) {
					self.popUp.goodInfo.changePoint = false;
					self.popUp.goodInfo.statusInformer = true;

					self.getOrder('renew');
				}
			});
		},

		// изменить пункт выдачи у заказа
		cancelOrder: function() {
			let self = this;

			axios.post(window.location.pathname, querystring.stringify({
				op: 'changeStatus',
				ID_ORDER: self.popUp.goodInfo.good.id,
				STATUS: 4,
			})).then(function ( res ) {
				if( res.data.response != undefined ) {
					self.popUp.goodInfo.isopen = false;
					self.popUp.orderDelete.isopen = false;

					self.getOrder('renew');
				}
			});
		},

		// отправить смс напоминание пользователю, что нужно забрать заказ
		resendSms: function() {
			let self = this;

			axios.post(window.location.pathname, querystring.stringify({
				op: 'resendSms',
				ID_ORDER: self.popUp.resendSms.idOrder,
			})).then(function ( res ) {
				if( res.data.response != undefined ) {
					self.popUp.resendSms.idOrder = 0;
				}
			});
		},
	},
	watch: {
		'filters.search': function(val, old) {
			this.getOrder('new');
		},
		'filters.dateFrom': function(val, old) {
			this.getOrder('new');
		},
		'filters.dateTo': function(val, old) {
			this.getOrder('new');
		},
		'filters.city': function(val, old) {
			this.getOrder('new');
		},
		'filters.status': function(val, old) {
			this.getOrder('new');
		},
		'filters.sort': function(val, old) {
			this.getOrder('new');
		},
		'popUp.goodInfo.good.status': function(val, old) {
			if( val != undefined && old != undefined ) {
				this.changeStatus(val);
			}
		},
		'popUp.goodInfo.statusInformer': function(val, old) {
			let self = this;

			if( val == true ) {
				setTimeout(() => {
					self.popUp.goodInfo.statusInformer = false;
				}, 2000);
			}
		},
	},
});
<?=CJSPACKER::end();?>
</script>


<? include ROOT.'/views/layouts/admin_footer.php'; ?>
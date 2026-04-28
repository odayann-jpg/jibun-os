<?php
App::uses('AppController', 'Controller');
App::uses('Model', 'Model');
class SalesController extends AppController{
	
	// 設定
	public $uses = array('Sale','SaleDetail','Member','Stock','InvoiceSetting','Purchase','Holiday','Lockedit');	// 使用モデル
	public $conditions = array();																				// 基本抽出条件
	public $order = array();																						// 基本並び順
	
	// beforeFilter
	public function beforeFilter(){
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		
		// 親へ
		parent::beforeFilter();
		
		// 短縮用変数宣言
		$this->tableName = $this->tables[$this->params['controller']];
		
		// 基本抽出条件指定
		$this->conditions = array($Model.'.is_delete' => 0);
		
		// 基本並び順指定
		$this->order = array(
			$Model.'.id' => 'DESC'
		);
		
		// 顧客一覧取得
		$params = array(
			'conditions' => array(
				'Member.is_delete' => 0,		// 削除フラグ（0:通常）
			),
			'order' => array(
				'Member.kana' => 'ASC',	
			),
			'recursive' => -1,
		);
		$temps = $this->Member->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $k => $v){
				if($v['Member']['is_billing']==1)		$this->choices[$Model]['member_id'][$v['Member']['id']] = $v['Member']['name'].(!empty($v['Member']['kana']) ? '('.$v['Member']['kana'].')' : '').' / ID:'.$v['Member']['id'];
				elseif($v['Member']['is_billing']==2)	$this->choices[$Model]['member_id'][$v['Member']['id']] = $v['Member']['name'].(!empty($v['Member']['kana']) ? '('.$v['Member']['kana'].')' : '').' / '.$v['Member']['company'].' / ID:'.$v['Member']['id'];
			}
		}
		
		// 在庫一覧取得
		$params = array(
			'conditions' => array(
				'Stock.is_delete' => 0,		// 削除フラグ（0:通常）
			),
			'order' => array(
				'Stock.code' => 'ASC',	
			),
			'recursive' => 0,
		);
		$this->Stock->unbindModel(array('belongsTo' => array('Brand','Supplier')));
		$temps = $this->Stock->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $k => $v){
				$this->choices[$dModel]['barcode'][$v['Stock']['barcode']] = $v['Stock']['barcode'];	// バーコード
				$this->choices[$dModel]['code'][$v['Stock']['barcode']] = $v['Stock']['code']			// 商品コード（商品コード）
					.(!empty($v['Stock']['name']) ? '/'.$v['Stock']['name'] : '')						// ＋品名
					.(!empty($v['Color']['name']) ? '/'.$v['Color']['name'] : '')						// ＋カラー
					.(!empty($v['Size']['name']) ? '/'.$v['Size']['name'] : '');						// ＋サイズ
				$this->choices[$dModel]['stock_id'][$v['Stock']['barcode']] = $v['Stock']['id'];		// 在庫ID
				$this->choices[$dModel]['stock_code'][$v['Stock']['id']] = $v['Stock']['code'];			// 在庫ID別:商品コード
				$this->choices[$dModel]['stock_name'][$v['Stock']['id']] = $v['Stock']['name'];			// 在庫ID別:品名
				$this->choices[$dModel]['stock_cost'][$v['Stock']['id']] = $v['Stock']['cost'];			// 在庫ID別:下代
				$this->choices[$dModel]['stock_price'][$v['Stock']['id']] = $v['Stock']['price'];		// 在庫ID別:上代
				
				$this->choices[$dModel]['receipt'][$v['Stock']['barcode']] = 							// 領収書用
					(!empty($v['Color']['name']) ? '/'.$v['Color']['name'] : '')						// ＋カラー
					.(!empty($v['Size']['name']) ? '/'.$v['Size']['name'] : '');						// ＋サイズ
				
			}
		}
		
		// 編集ロック対象日取得
		$this->lockDate = $this->EditLockDateGet();
		
		// 変数宣言
		$this->set('lockDate',$this->lockDate);
		
	}
	
	// データ移行
	public function transfer(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 顧客コード一覧取得
		$transfer_mcodes = array();
		$SQL = "SELECT member_id,col1 FROM members_CSV WHERE is_transfer != 0;";
		$result = $this->$Model->query($SQL);
		if(!empty($result)){
			foreach($result as $temp) $transfer_mcodes[$temp['members_CSV']['col1']] = $temp['members_CSV']['member_id'];
		}
		
		// CSVデータ取得（売上）
		$SQL = "SELECT * FROM sales_CSV WHERE is_transfer = 0 GROUP BY col1 ASC , col2 ASC ORDER BY col4 ASC , col7 ASC LIMIT 1000;";
		$result = $this->$Model->query($SQL);
		foreach($result as $v){
			
			// 初期値
			$sale = array(
				'stotal' => 0,
				'tax_amount' => 0,
				'bill_amount' => 0,
				'cost_total' => 0,
			);
			$sale_details = array();
			
			// 変数移行
			$line = $v['sales_CSV'];
			
			// col1:登録日
			$sale['created'] = $sale['modified'] = date('Y-m-d 00:00:00',strtotime($line['col1']));
			
			// col2:伝票番号
			$sale['number'] = $line['col2'];
			$sale['transfer_scode'] = $line['col2'];
			
			// col4:伝票日付 ／ col7:時刻
			$sale['date'] = date('Y-m-d',strtotime($line['col4']));
			$sale['deposit_date'] = date('Y-m-t',strtotime($line['col4']));
			
			// col11:記入者名
			switch($line['col11']){
				case '西岡　慎也':						$sale['reporter_id'] = 3;	break;
				case '吉川　浩太郎':					$sale['reporter_id'] = 5;	break;
				case '島野　淳':						$sale['reporter_id'] = 6;	break;
				case '菊地　翔平':	case '菊地翔平':	$sale['reporter_id'] = 7;	break;
				case '立花　由佳':						$sale['reporter_id'] = 8;	break;
				case '道清　雅紀':						$sale['reporter_id'] = 9;	break;
				case '福原 宗之':	case '福原　宗之':	$sale['reporter_id'] = 10;	break;
				case '監物　知也':						$sale['reporter_id'] = 11;	break;
				case '斉藤 巧':		case '斉藤　巧':	$sale['reporter_id'] = 12;	break;
				case '頼富　雄介':						$sale['reporter_id'] = 13;	break;
				case '重松 真吾':						$sale['reporter_id'] = 14;	break;
				case '相馬　直行':						$sale['reporter_id'] = 15;	break;
				case '香遠 理香':						$sale['reporter_id'] = 16;	break;
			}
			
			// col12:顧客コード
			$sale['transfer_mcode'] = $line['col12'];
			$sale['member_id'] = (!empty($transfer_mcodes[$sale['transfer_mcode']]) ? $transfer_mcodes[$sale['transfer_mcode']] : 0);
			
			// col15:担当者名
			switch($line['col15']){
				case '西岡　慎也':						$sale['charge_id'] = 3;	break;
				case '吉川　浩太郎':					$sale['charge_id'] = 5;	break;
				case '島野　淳':						$sale['charge_id'] = 6;	break;
				case '菊地　翔平':	case '菊地翔平':	$sale['charge_id'] = 7;	break;
				case '立花　由佳':						$sale['charge_id'] = 8;	break;
				case '道清　雅紀':						$sale['charge_id'] = 9;	break;
				case '福原 宗之':	case '福原　宗之':	$sale['charge_id'] = 10; break;
				case '監物　知也':						$sale['charge_id'] = 11; break;
				case '斉藤 巧':		case '斉藤　巧':	$sale['charge_id'] = 12; break;
				case '頼富　雄介':						$sale['charge_id'] = 13; break;
				case '重松 真吾':						$sale['charge_id'] = 14; break;
				case '相馬　直行':						$sale['charge_id'] = 15; break;
				case '香遠 理香':						$sale['charge_id'] = 16; break;
				case '平木　元基':						$sale['charge_id'] = 17; break;
			}
			
			// col21:お預かり
			$sale['deposit_amount'] = $line['col21'];
			
			// col22:お釣り
			$sale['change_amount'] = $line['col22'];
			
			// col29:税率
			$sale['tax_rate'] = $line['col29'];
			
			// col30:備考
			if(!empty($line['col30'])) $sale['memo'] = $line['col30'];
			
			// col53:入金区分
			switch($line['col53']){
				case 'クレジット(ネット)':	case 'ｸﾚｼﾞｯﾄ':	$sale['is_payment'] = 1; $sale['is_type'] = 1; break;
				case '内金_ｸﾚｼﾞｯﾄ':							$sale['is_payment'] = 1; $sale['is_type'] = 3; break;
//				case '':									$sale['is_payment'] = 2; $sale['is_type'] = 1; break;
				case '内金_現金':							$sale['is_payment'] = 3; $sale['is_type'] = 3; break;
				case '売掛入金:現金':						$sale['is_payment'] = 3; $sale['is_type'] = 2; break;
				case '現金':								$sale['is_payment'] = 3; $sale['is_type'] = 1; break;
				case '電子マネー':							$sale['is_payment'] = 4; $sale['is_type'] = 1; break;
				case '商品券':								$sale['is_payment'] = 9; $sale['is_type'] = 1; break;
				case '売掛入金:現金以外':					$sale['is_payment'] = 9; $sale['is_type'] = 2; break;
			}
			
			
			// col54:入金日付
			if($line['col54']!="00/00/00") $sale['payment_date'] = date('Y-m-d',strtotime($line['col54']));
			
			// col55:入金額
			$sale['deposit_amount'] = $line['col55'];
			
			// CSVデータ取得（売上詳細）
			$SQL2 = "SELECT * FROM sales_CSV WHERE col2 = '".$line['col2']."' ORDER BY col31 ASC;";
			$result2 = $this->$Model->query($SQL2);
			foreach($result2 as $v2){
				
				// 初期値
				$sale_detail = array();
				
				// 変数移行
				$line2 = $v2['sales_CSV'];
				
				// col2:伝票番号
				$sale_detail['transfer_scode'] = $line2['col2'];
				
				// col12:顧客コード
				$sale_detail['transfer_mcode'] = $line2['col12'];
				
				// col30:備考
				if(!empty($line2['col30'])) $sale_detail['memo'] = $line2['col30'];
				
				// col31:行番号
				$sale_detail['no'] = $line2['col31'];
				
				// col34:商品コード
				if(!empty($line2['col34'])) $sale_detail['code'] = $line2['col34'];
				
				// col35:商品名
				if(!empty($line2['col35'])) $sale_detail['name'] = trim(str_replace("　","",mb_convert_kana($line2['col35'],"KVC")));
				
				// col37:バーコード
				if(!empty($line2['col37'])) $sale_detail['barcode'] = $line2['col37'];
				
				// col42:数量
				$sale_detail['quantity'] = (int)$line2['col42'];
				
				// col46:原価金額
				$sale['cost_total'] += (int)$line2['col46'];
				
				// col48:上代単価
				$sale_detail['price'] = (int)$line2['col48'];
				
				// col49:上代金額税抜
				$sale_detail['total'] = (int)$line2['col49'];
				$sale['stotal'] += $line2['col49'];
				
				// col50:上代金額税額
				$sale['tax_amount'] += $line2['col50'];
				
				// col51:上代金額税込
				$sale['bill_amount'] += $line2['col51'];
				
				// 変数格納
				$sale_details[$line2['id']] = $sale_detail;
				
			}
			
			// データ調整
			$sale['is_reflect'] = 1;
			$sale['is_send'] = 1;
			$sale['is_deposit'] = 99;
			$sale['is_transfer'] = 1;
			
			// 請求書設定取得
			$temp = $this->InvoiceSetting->read(null,1);
			if(!empty($temp)){
				foreach($temp['InvoiceSetting'] as $column => $value){
					if($column!="id" && $column!="modified" && $column!="created" && $column!="modify_id" && $column!="create_id") $sale[$column] = $value;
				}
			}
			
			echo '<pre>';
			var_dump($sale['transfer_scode']);
			echo '</pre>';
			
			// データ保存（売上）
			$this->$Model->create();
			if($this->$Model->save($sale)){
				
				// 売上ID取得
				$sale_id = $this->$Model->getInsertID();
				
				// 売上詳細分ループ
				foreach($sale_details as $sale_detail){
					
					// データ調整
					$sale_detail['sale_id'] = $sale_id;
					$sale_detail['is_transfer'] = 1;
					$sale_detail['unit'] = "式";
					
					echo '<pre>';
					var_dump($sale_detail['no']);
					echo '</pre>';
					
					// データ保存（売上詳細）
					$this->$Model->SaleDetail->create();
					if($this->$Model->SaleDetail->save($sale_detail)){
						
						// 売上詳細ID取得
						$sale_detail['id'] = $this->$Model->SaleDetail->getInsertID();
						
						// データ更新（売上CSV）
						$SQL = "UPDATE sales_CSV SET is_transfer = ".$sale_detail['is_transfer']." , sale_id = ".$sale_detail['sale_id']." , sale_detail_id = ".$sale_detail['id']." WHERE col2 = ".$sale_detail['transfer_scode']." AND col31 = ".$sale_detail['no'].";";
						$this->$Model->query($SQL);
						
					}
					
				}
				
			}
			
		}
		exit;
		
	}
	
	// 一覧
	public function index(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// ページ設定
		$this->set('title','全'.$this->tableName.'一覧');
		
		// 初期値
		$conditions = array();
		
		// 外部ページからの指定
		if(!empty($_GET['mode']) && $_GET['mode']=="filter"){
			if(!empty($_GET['sdate'])) $this->request->data[$Model]['sdate'] = $_GET['sdate'];
			if(!empty($_GET['edate'])) $this->request->data[$Model]['edate'] = $_GET['edate'];
			if(!empty($_GET['charge'])) $this->request->data[$Model]['charge_id'] = $_GET['charge'];
			$_GET['mode'] = 'search';
		}
		
		// 絞り込み
		if($this->Common->modeCheck('search')){
			
			// キーワード検索
			if(!empty($this->request->data[$Model]['keyword'])){
				$keywords = $this->Common->explodeEx(' ',$this->request->data[$Model]['keyword']);
				foreach($keywords as $keyword) $conditions[] = am($conditions,array('OR' => array(
					$Model.'.number LIKE "%'.$keyword.'%"',		// 請求書番号
					$Model.'.name LIKE "%'.$keyword.'%"',		// タイトル
					$Model.'.memo LIKE "%'.$keyword.'%"',		// 備考
					'Member.name LIKE "%'.$keyword.'%"',		// お名前
					'Member.kana LIKE "%'.$keyword.'%"',		// フリガナ
					'Member.company LIKE "%'.$keyword.'%"',		// 会社名
				)));
			}
			
			// 請求日（日付前後チェック）
			if(!empty($this->request->data[$Model]['sdate']) && !empty($this->request->data[$Model]['edate']) && $this->request->data[$Model]['sdate'] > $this->request->data[$Model]['edate']){ $temp = $this->request->data[$Model]['sdate']; $this->request->data[$Model]['sdate'] = $this->request->data[$Model]['edate']; $this->request->data[$Model]['edate'] = $temp; }
			if(!empty($this->request->data[$Model]['sdate'])) $conditions = am($conditions,array($Model.'.date >= ' => $this->request->data[$Model]['sdate']));
			if(!empty($this->request->data[$Model]['edate'])) $conditions = am($conditions,array($Model.'.date <= ' => $this->request->data[$Model]['edate']));
			
			// 入金期限（日付前後チェック）
			if(!empty($this->request->data[$Model]['dsdate']) && !empty($this->request->data[$Model]['dedate']) && $this->request->data[$Model]['dsdate'] > $this->request->data[$Model]['dedate']){ $temp = $this->request->data[$Model]['dsdate']; $this->request->data[$Model]['dsdate'] = $this->request->data[$Model]['dedate']; $this->request->data[$Model]['dedate'] = $temp; }
			if(!empty($this->request->data[$Model]['dsdate'])) $conditions = am($conditions,array($Model.'.deposit_date >= ' => $this->request->data[$Model]['dsdate']));
			if(!empty($this->request->data[$Model]['dedate'])) $conditions = am($conditions,array($Model.'.deposit_date <= ' => $this->request->data[$Model]['dedate']));
			
			// 入金日（日付前後チェック）
			if(!empty($this->request->data[$Model]['psdate']) && !empty($this->request->data[$Model]['pedate']) && $this->request->data[$Model]['psdate'] > $this->request->data[$Model]['pedate']){ $temp = $this->request->data[$Model]['psdate']; $this->request->data[$Model]['psdate'] = $this->request->data[$Model]['pedate']; $this->request->data[$Model]['pedate'] = $temp; }
			if(!empty($this->request->data[$Model]['psdate'])) $conditions = am($conditions,array($Model.'.payment_date >= ' => $this->request->data[$Model]['psdate']));
			if(!empty($this->request->data[$Model]['pedate'])) $conditions = am($conditions,array($Model.'.payment_date <= ' => $this->request->data[$Model]['pedate']));
			
			// 商品コード(文字列検索)
			if(!empty($this->request->data[$Model]['codeEx'])){
				$params = array(
					'conditions' => array(
						$Model.'Detail.code LIKE "'.str_replace("*","%",$this->request->data[$Model]['codeEx']).'"',	// 商品コード
						$Model.'Detail.is_delete' => 0,																					// 削除フラグ
					),
					'fields' => array($Model.'Detail.sale_id'),
					'group' => array($Model.'Detail.sale_id'),
					'recursive' => -1,
				);
				$ids = $this->$Model->SaleDetail->find('list',$params);
				$conditions = am($conditions,array($Model.'.id IN ('.implode(',',(!empty($ids) ? $ids : array(0))).')'));
			}
			
			// 顧客ID
			if(!empty($this->request->data[$Model]['member_id'])) $conditions = am($conditions,array($Model.'.member_id' => $this->request->data[$Model]['member_id']));
			
			// 請求金額
			if(!empty($this->request->data[$Model]['bill_amount'])) $conditions = am($conditions,array($Model.'.bill_amount' => $this->request->data[$Model]['bill_amount']));
			
			// 担当ユーザー
			if(isset($this->request->data[$Model]['charge_id']) && $this->request->data[$Model]['charge_id']!=="") $conditions = am($conditions,array($Model.'.charge_id' => $this->request->data[$Model]['charge_id']));
			
			// 売上種別
			if(isset($this->request->data[$Model]['is_type']) && $this->request->data[$Model]['is_type']!=="") $conditions = am($conditions,array($Model.'.is_type' => $this->request->data[$Model]['is_type']));
			
			// 入金回数
			if(isset($this->request->data[$Model]['is_pay_plural']) && $this->request->data[$Model]['is_pay_plural']!=="") $conditions = am($conditions,array($Model.'.is_pay_plural' => $this->request->data[$Model]['is_pay_plural']));
			
			// 支払方法
			if(isset($this->request->data[$Model]['is_payment']) && $this->request->data[$Model]['is_payment']!=="") $conditions = am($conditions,array($Model.'.is_payment' => $this->request->data[$Model]['is_payment']));
			
			// 売上登録
			if(isset($this->request->data[$Model]['is_reflect']) && $this->request->data[$Model]['is_reflect']!=="") $conditions = am($conditions,array($Model.'.is_reflect' => $this->request->data[$Model]['is_reflect']));
			
			// 請求書送付
			if(isset($this->request->data[$Model]['is_send']) && $this->request->data[$Model]['is_send']!=="") $conditions = am($conditions,array($Model.'.is_send' => $this->request->data[$Model]['is_send']));
			
			// オプション
			if(isset($this->request->data[$Model]['option']) && $this->request->data[$Model]['option']!==""){
				foreach($this->request->data[$Model]['option'] as $option){
					switch($option){
						
						// 入金待ちのみ表示
						case 'paywait_only':
							$conditions = am($conditions,array(
								$Model.'.is_deposit !=' => array(99,88), 	// 入金消込（99:不明以外 かつ 88:値引き以外）
								'OR' => array(
									$Model.'.is_deposit' => 0,			// 入金消込（0:入金待ち）
									$Model.'.unpaid_amount > ' => 0,	// 未入金（0円超）
								),
							));
							break;
							
					}
				}
			}
			
			// セッション保存
			$this->Session->write($this->viewPath.$this->action.'Data',$this->request->data);
			$this->Session->write($this->viewPath.$this->action.'Conditions',$conditions);
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		// 条件クリア
		}elseif(!empty($_GET['mode']) && $_GET['mode']=="clear"){
			
			// セッション破棄
			$this->Session->delete($this->viewPath.$this->action.'Data');
			$this->Session->delete($this->viewPath.$this->action.'Conditions');
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		// セッションチェック
		}elseif($this->Session->check($this->viewPath.$this->action.'Data')){
			
			// セッション取得
			$this->request->data = $this->Session->read($this->viewPath.$this->action.'Data');
			$conditions = $this->Session->read($this->viewPath.$this->action.'Conditions');
			
		}
		
		// フィルターがある場合
		if(!empty($_GET['filter'])) $conditions = am($conditions,array($Model.'.is_'.$_GET['filter'] => 0));
		
		// ページネート設定
		$this->paginate = array(
			$Model => array(
				'conditions' => am($this->conditions,$conditions),		// 抽出条件
				'order' => $this->order,								// 並び順
				'limit' => 20,											// 表示件数
				'recursive' => 1,										// 再帰階層
				'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
			),
		);
		
		// データ取得
		$this->$Model->unbindModel(array('hasMany' => array('SaleDetail')));
		$data =  $this->paginate($Model);
		
		// 変数宣言
		$this->set(compact('data'));
		
	}
	
	// 入金消込
	public function deposit(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// ページ設定
		$this->set('title','入金消込');
		
		// 初期値
		$conditions = array();
		
		// 絞り込み
		if($this->Common->modeCheck('search')){
			
			// キーワード検索
			if(!empty($this->request->data[$Model]['keyword'])){
				$keywords = $this->Common->explodeEx(' ',$this->request->data[$Model]['keyword']);
				foreach($keywords as $keyword) $conditions[] = am($conditions,array('OR' => array(
					$Model.'.number LIKE "%'.$keyword.'%"',		// 請求書番号
					$Model.'.name LIKE "%'.$keyword.'%"',		// タイトル
					$Model.'.memo LIKE "%'.$keyword.'%"',		// 備考
					'Member.name LIKE "%'.$keyword.'%"',		// お名前
					'Member.kana LIKE "%'.$keyword.'%"',		// フリガナ
					'Member.company LIKE "%'.$keyword.'%"',		// 会社名
				)));
			}
			
			// 請求日（日付前後チェック）
			if(!empty($this->request->data[$Model]['sdate']) && !empty($this->request->data[$Model]['edate']) && $this->request->data[$Model]['sdate'] > $this->request->data[$Model]['edate']){ $temp = $this->request->data[$Model]['sdate']; $this->request->data[$Model]['sdate'] = $this->request->data[$Model]['edate']; $this->request->data[$Model]['edate'] = $temp; }
			if(!empty($this->request->data[$Model]['sdate'])) $conditions = am($conditions,array($Model.'.date >= ' => $this->request->data[$Model]['sdate']));
			if(!empty($this->request->data[$Model]['edate'])) $conditions = am($conditions,array($Model.'.date <= ' => $this->request->data[$Model]['edate']));
			
			// 顧客ID
			if(!empty($this->request->data[$Model]['member_id'])) $conditions = am($conditions,array($Model.'.member_id' => $this->request->data[$Model]['member_id']));
			
			// 担当ユーザー
			if(isset($this->request->data[$Model]['charge_id']) && $this->request->data[$Model]['charge_id']!=="") $conditions = am($conditions,array($Model.'.charge_id' => $this->request->data[$Model]['charge_id']));
			
			// 入金消込
			if(isset($this->request->data[$Model]['is_deposit']) && $this->request->data[$Model]['is_deposit']!=="") $conditions = am($conditions,array($Model.'.is_deposit' => $this->request->data[$Model]['is_deposit']));
			
			// セッション保存
			$this->Session->write($this->viewPath.$this->action.'Data',$this->request->data);
			$this->Session->write($this->viewPath.$this->action.'Conditions',$conditions);
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/deposit/');
			exit;
			
		// 条件クリア
		}elseif(!empty($_GET['mode']) && $_GET['mode']=="clear"){
			
			// セッション破棄
			$this->Session->delete($this->viewPath.$this->action.'Data');
			$this->Session->delete($this->viewPath.$this->action.'Conditions');
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/deposit/');
			exit;
			
		// セッションチェック
		}elseif($this->Session->check($this->viewPath.$this->action.'Data')){
			
			// セッション取得
			$this->request->data = $this->Session->read($this->viewPath.$this->action.'Data');
			$conditions = $this->Session->read($this->viewPath.$this->action.'Conditions');
			
		}
		
		// 入金状況「入金待ち」または入金日なし、移行データじゃない
		$conditions = am($conditions,array('OR' => array($Model.'.is_deposit' => 0,$Model.'.payment_date IS NULL'),$Model.'.is_transfer' => 0));
		
		// ページネート設定
		$this->paginate = array(
			$Model => array(
				'conditions' => am($this->conditions,$conditions),		// 抽出条件
				'order' => $this->order,								// 並び順
				'limit' => 20,											// 表示件数
				'recursive' => 0,										// 再帰階層
				'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
			),
		);
		
		// データ取得
		$data =  $this->paginate($Model);
		
		// 変数宣言
		$this->set(compact('data'));
		
	}
	
	// 変更
	public function change($id = null , $column = null , $value = null){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$response = array('result' => 'NG');
		
		// データチェック
		if(!empty($id) && !empty($column)){
			
			// データ更新（各値）
			$this->$Model->id = $id;
			$this->$Model->saveField($column,$value);
			
			// 処理結果
			$response['result'] = 'OK';
			
		}
		
		// リターン
		echo json_encode($response);
		Configure::write('debug',0); exit;
		
	}
	
	// お預かり登録
	public function deposit_amount($id = null , $amount = null){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$response = array('result' => 'NG');
		
		// データチェック
		if(!empty($id)){
			
			// データ取得
			$params = array(
				'conditions' => am($this->conditions,array(		// 抽出条件
					$Model.'.id' => $id,						// ID
				)),
				'recursive' => -1,								// 再帰階層
			);
			$detail = $this->$Model->find('first',$params);
			if(!empty($detail)){
				
				// 数値化
				$amount = (int)$amount;
				
				// データ更新
				$this->$Model->id = $id;
				$this->$Model->saveField('deposit_amount',$amount);										// お預かり
				$this->$Model->saveField('change_amount',$amount - $detail[$Model]['bill_amount']);		// お釣り
				$this->$Model->saveField('unpaid_amount',$detail[$Model]['bill_amount'] - $amount);		// 未入金
				
				// 処理結果
				$response['result'] = 'OK';
				
			}
			
		}
		
		// リターン
		echo json_encode($response);
		Configure::write('debug',0); exit;
		
	}
	
	// 入金日登録
	public function payment_date($id = null , $date = null){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$response = array('result' => 'NG');
		
		// データチェック
		if(!empty($id)){
			
			// データ更新（入金日）
			$this->$Model->id = $id;
			$this->$Model->saveField('payment_date',$date);
			
			// 処理結果
			$response['result'] = 'OK';
			
		}
		
		// リターン
		echo json_encode($response);
		Configure::write('debug',0); exit;
		
	}
	
	// 登録/編集
	public function edit($id = null){
		
		// ページ設定
		$this->set('title',$this->tableName.(!empty($id) ? '編集' : '新規登録'));
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		$pModel = $Model.'Pay';
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// ファイル仮アップロード
			foreach(array('inhouse_comseal') as $fld){
				if(!empty($this->request->data[$Model][$fld.'_upload']['name'])){
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$Model][$fld.'_upload'],true);
					if(empty($errorMessage)) $this->request->data[$Model][$fld] = $fileName; else $errors[$fld] = $errorMessage;
				}
				unset($this->request->data[$Model][$fld.'_upload']);
			}
			
			// エラーがない場合
			if(empty($errors)){
				
				// 編集ロックチェック（編集ロック中じゃないまたはロック解除パスワード一致）
				if($this->request->data[$Model]['date'] > $this->lockDate || (!empty($this->request->data[$Model]['release_pass']) && $this->request->data[$Model]['release_pass']==EDIT_LOCK_PASSWORD)){
					
					// ファイル本アップロード（移動／削除）
					foreach(array('inhouse_comseal') as $fld){
						if(!empty($this->request->data[$Model][$fld])){
							if(strpos($this->request->data[$Model][$fld],"temporary_")!==FALSE)	$this->request->data[$Model][$fld] = $this->File->confirmed($this->request->data[$Model][$fld]);
							elseif($this->request->data[$Model][$fld]=="delete")						$this->request->data[$Model][$fld] = '';
						}else $this->request->data[$Model][$fld] = '';
					}
					
					// 新規登録の場合
					if(empty($id)){
						
						// 請求書番号
						$saleCount = $this->$Model->find('count');
						$this->request->data[$Model]['number'] = 'INV'.str_pad($saleCount + 1,7,'0',STR_PAD_LEFT);
						
					}
					
					// 未入金再計算
					$this->request->data[$Model]['unpaid_amount'] = $this->request->data[$Model]['bill_amount'] - $this->request->data[$Model]['deposit_amount'];
					if($this->request->data[$Model]['unpaid_amount'] < 0) $this->request->data[$Model]['unpaid_amount'] = 0;
					
					// データ保存
					if(empty($id)) $this->$Model->create();
					if($this->$Model->save($this->request->data)){
						
						// アラート
						if(empty($id))	$this->Common->alertSet('新しい'.$this->tableName.(!empty($this->request->data[$Model]['name']) ? '（'.$this->request->data[$Model]['name'].'）' : '').'を登録しました','success');
						else			$this->Common->alertSet($this->tableName.(!empty($this->request->data[$Model]['name']) ? '（'.$this->request->data[$Model]['name'].'）' : '').'の編集内容を保存しました','success');
						
						// ID取得
						if(empty($id)) $id = $this->$Model->getInsertID();
						
						// 顧客ID取得
						$member_id = $this->request->data[$Model]['member_id'];
						
						// 売上詳細欄チェック
						if(!empty($this->request->data[$dModel])){
							$no = 0;
							foreach($this->request->data[$dModel] as $k => $v){ if($k!="dummy"){
								
								// 編集または（品名ありかつ未削除）
								if(!empty($v['id']) || (!empty($v['name']) && empty($v['is_delete']))){
									
									// データ準備（売上詳細）
									$v['sale_id'] = $id;
									$v['no'] = ++$no;
									
									// データ準備（商品コード反映）
									if(!empty($v['code'])) $v['code'] = $this->choices[$dModel]['stock_code'][$this->choices[$dModel]['stock_id'][$v['code']]];
									
									// データ保存（欄）
									$sModel = $dModel;
									if(empty($v['id'])) $this->$Model->$sModel->create();
									$this->$Model->$sModel->save($v);
									
								}
							
							}}
						}
						
						// 入力履歴欄チェック
						if(!empty($this->request->data[$pModel])){
							$no = 0;
							foreach($this->request->data[$pModel] as $k => $v){ if($k!="dummy"){
								
								// 編集または（入金日ありかつ未削除）
								if(!empty($v['id']) || (!empty($v['date']) && empty($v['is_delete']))){
									
									// データ準備（売上詳細）
									$v['sale_id'] = $id;
									$v['no'] = ++$no;
									
									// データ保存（欄）
									$sModel = $pModel;
									if(empty($v['id'])) $this->$Model->$sModel->create();
									$this->$Model->$sModel->save($v);
									
								}
							
							}}
						}
						
						// ロック編集コメントチェック
						if(!empty($this->request->data[$Model]['edit_comment'])){
							
							// データ保存（ロック時編集履歴）
							$this->Lockedit->create();
							$this->Lockedit->save(array(
								'model' => $Model,											// モデル
								'target_id' => $id,											// 対象データID
								'date' => date('Y-m-d H:i:s'),						// 編集日時
								'editor' => $this->request->data[$Model]['edit_name'],		// 編集担当者
								'comment' => $this->request->data[$Model]['edit_comment'],	// 変更点
							));
							
						}
						
						// 在庫データ更新（最終出庫日／出庫数／在庫数／在庫額）
						$SQL = "UPDATE stocks AS S SET  ";
						$SQL.= "issue_date = (SELECT MAX(U.date) FROM ((SELECT MAX(purchases.date) AS date ,purchases.barcode AS barcode FROM purchases WHERE is_stock = 1 AND purchases.is_delete = 0 GROUP BY purchases.barcode) UNION  ";
						$SQL.= "(SELECT MAX(sales.date) AS date , sale_details.barcode AS barcode FROM sale_details LEFT JOIN sales ON sales.id = sale_details.sale_id WHERE sales.is_transfer = 0 AND sales.is_delete = 0 AND sale_details.is_transfer = 0 AND sale_details.is_delete = 0 GROUP BY sale_details.barcode)) AS U WHERE U.barcode = S.barcode), "; 
						$SQL.= "purchase_count = (SELECT SUM(purchases.quantity) AS quantity FROM purchases WHERE purchases.barcode = S.barcode AND purchases.is_stock = 1 AND purchases.is_delete = 0 GROUP BY purchases.barcode), ";
						$SQL.= "sale_count = (SELECT SUM(sale_details.quantity) AS quantity  FROM sale_details LEFT JOIN sales ON sales.id = sale_details.sale_id WHERE sale_details.barcode = S.barcode AND sales.is_transfer = 0 AND sales.is_delete = 0 AND sale_details.is_transfer = 0 AND sale_details.is_delete = 0 GROUP BY sale_details.barcode), "; 
						$SQL.= "issue_count = purchase_count + sale_count, ";
						$SQL.= "stock_count = (CASE WHEN is_intangible = 0 THEN quantity - issue_count + adjust_count ELSE 0 END) , stock_amount = stock_count * cost ";
						$SQL.= "WHERE S.is_delete = 0;";
						$this->$Model->query($SQL);
						
						// 売上データ更新（下代合計）
						$SQL = "UPDATE sales AS S SET cost_total = (SELECT IFNULL(SUM(ST.cost * SD.quantity),0) FROM sale_details AS SD LEFT JOIN stocks AS ST ON SD.barcode = ST.barcode WHERE SD.sale_id = S.id AND SD.barcode != '') WHERE S.is_delete = 0 AND S.is_transfer = 0;";
						$this->$Model->query($SQL);
						
						// リダイレクト
						if(!empty($_POST['submit']) && $_POST['submit']=="保存して詳細画面へ")			$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');		// 詳細画面へ
						elseif(!empty($_POST['submit']) && $_POST['submit']=="保存して顧客画面へ")		$this->redirect('/members/view/'.$member_id.'/');						// 顧客詳細画面へ
						else																			$this->redirect('/'.$this->params['controller'].'/');					// 一覧画面へ
						exit;
						
					}
					
				}elseif(!empty($this->request->data[$Model]['release_pass'])){
					
					// アラート
					$this->Common->alertSet('入力内容が正しくありません。再度確認してください','danger');
					
					// エラー
					$errors['release_pass'] = 'パスワードが正しくありません';
					
					// 変数宣言
					$this->set(compact('errors'));
					
				}
				
			// エラーがある場合
			}else{
				
				// アラート
				$this->Common->alertSet('入力内容が正しくありません。再度確認してください','danger');
				
				// 変数宣言
				$this->set(compact('errors'));
				
			}
			
		}elseif(empty($this->request->data)){
			
			// 編集時 or 複製時
			if(!empty($id) || !empty($_GET['copy'])){
				
				// データ取得
				$params = array(
					'conditions' => am($this->conditions,array(		// 抽出条件
						$Model . '.id' => $id,						// ID
					)),
					'recursive' => 1,								// 再帰階層
				);
				$this->request->data = $this->$Model->find('first',$params);
				if(empty($this->request->data)){
					
					// リダイレクト（一覧画面へ）
					$this->redirect('/'.$this->params['controller'].'/');
					exit;
					
				}
				
				// 編集ロックチェック（管理者以外かつ編集ロック中）
				if($this->auth['is_power']!=1 && $this->request->data[$Model]['date'] <= $this->lockDate){
					
					// アラート
					$this->Common->alertSet("この".$this->tableName.'は編集ロック中です','danger');
					
					// リダイレクト（詳細画面へ）
					$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
					exit;
					
				}
				
				// 変数移行（売上詳細）
				if(!empty($this->request->data[$dModel])){
					$temps = $this->request->data[$dModel]; unset($this->request->data[$dModel]);
					foreach($temps as $temp){
						if(!empty($temp['code'])) $temp['code'] = $temp['barcode'];	// バーコード反映
						$this->request->data[$dModel][$temp['id']] = $temp;
					}
				}else{
					$this->request->data[$dModel] = array(
						time() => array(
							'name' => '',
							'quantity' => 1,
							'unit' => '式',
							'price' => '0',
							'total' => '0',
							'memo' => '',
						),
					);
				}
				
				// 変数移行（入金履歴）
				if(!empty($this->request->data[$pModel])){
					$temps = $this->request->data[$pModel]; unset($this->request->data[$pModel]);
					foreach($temps as $temp){
						$this->request->data[$pModel][$temp['id']] = $temp;
					}
				}else{
					$this->request->data[$pModel] = array(
						time() => array(
							'date' => '',
							'price' => '0',
							'is_deposit' => '0',
							'memo' => '',
						),
					);
				}
				
			// 新規登録時
			}else{
				
				// デフォルト値格納
				$schema = $this->$Model->schema();
				if(!empty($schema)){
					foreach($schema as $column => $schem){
						
						// 変数格納
						$this->request->data[$Model][$column] = $schem['default'];
						
					}
				}
				
				// 請求書設定取得
				$temp = $this->InvoiceSetting->read(null,1);
				if(!empty($temp)){
					foreach($temp['InvoiceSetting'] as $column => $value){
						if($column!="id") $this->request->data[$Model][$column] = $value;
					}
				}
				
				// 初期化
				$this->request->data[$Model]['date'] = date('Y-m-d');
				$this->request->data[$Model]['deposit_date'] = date('Y-m-t');
				$this->request->data[$Model]['reporter_id'] = $this->auth['id'];
				$this->request->data[$Model]['tax_rate'] = TAX_RATE;
				
				// 顧客管理からの場合
				if(!empty($_GET['member'])) $this->request->data[$Model]['member_id'] = $_GET['member'];
				
				// 初期値（売上詳細）
				$this->request->data[$dModel] = array();
				for($i=1;$i<=6;$i++) $this->request->data[$dModel] += array(
					mktime().$i => array(
						'name' => '',
						'quantity' => 1,
						'unit' => '式',
						'price' => '0',
						'total' => '0',
						'memo' => '',
					),
				);
				
				// 初期値（入金履歴）
				$this->request->data[$pModel] = array();
				$this->request->data[$pModel] += array(
					mktime() => array(
						'date' => '',
						'price' => '0',
						'is_deposit' => '0',
						'memo' => '',
					),
				);
				
			}
			
		}
		
		// ダミー生成（売上詳細）
		$this->request->data[$dModel] = array(
			'dummy' => array(
				'name' => '',
				'quantity' => 1,
				'unit' => '式',
				'price' => '0',
				'total' => '0',
				'memo' => '',
			),
		) + $this->request->data[$dModel];
		
		// ダミー生成（入金履歴）
		$this->request->data[$pModel] = array(
			'dummy' => array(
				'date' => '',
				'price' => '0',
				'is_deposit' => '0',
				'memo' => '',
			),
		) + $this->request->data[$pModel];
		
		// 変数宣言
		$this->set(compact('id'));
		
	}
	
	// 登録/編集
	public function advance(){
		
		// ページ設定
		$this->set('title','前受金一括登録');
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// ファイル仮アップロード
			foreach(array('inhouse_comseal') as $fld){
				if(!empty($this->request->data[$Model][$fld.'_upload']['name'])){
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$Model][$fld.'_upload'],true);
					if(empty($errorMessage)) $this->request->data[$Model][$fld] = $fileName; else $errors[$fld] = $errorMessage;
				}
				unset($this->request->data[$Model][$fld.'_upload']);
			}
			
			// エラーがない場合
			if(empty($errors)){
				
				// ファイル本アップロード（移動／削除）
				foreach(array('inhouse_comseal') as $fld){
					if(!empty($this->request->data[$Model][$fld])){
						if(strpos($this->request->data[$Model][$fld],"temporary_")!==FALSE)	$this->request->data[$Model][$fld] = $this->File->confirmed($this->request->data[$Model][$fld]);
						elseif($this->request->data[$Model][$fld]=="delete")						$this->request->data[$Model][$fld] = '';
					}else $this->request->data[$Model][$fld] = '';
				}
				
				// 総売上数取得
				$saleCount = $this->$Model->find('count');
				
				// 未入金再計算
				$this->request->data[$Model]['unpaid_amount'] = $this->request->data[$Model]['bill_amount'] - $this->request->data[$Model]['deposit_amount'];
				if($this->request->data[$Model]['unpaid_amount'] < 0) $this->request->data[$Model]['unpaid_amount'] = 0;
				
				// 欄チェック
				if(!empty($this->request->data[$dModel])){
					$no = 0;
					foreach($this->request->data[$dModel] as $k => $v){ if($k!="dummy"){
						
						// 請求書番号
						$this->request->data[$Model]['number'] = 'INV'.str_pad(++$saleCount,7,'0',STR_PAD_LEFT);
						
						// 金額反映/計算
						$this->request->data[$Model]['stotal'] = $v['total'];
						$this->request->data[$Model]['tax_amount'] = $v['total'] * ($this->request->data[$Model]['tax_rate']/100);
						$this->request->data[$Model]['bill_amount'] = $this->request->data[$Model]['stotal'] + $this->request->data[$Model]['tax_amount'];
						if($this->request->data[$Model]['deposit_amount'] > 0) $this->request->data[$Model]['deposit_amount'] = $this->request->data[$Model]['bill_amount'];
						if($this->request->data[$Model]['change_amount'] > 0) $this->request->data[$Model]['change_amount'] = $this->request->data[$Model]['bill_amount'];
						if($this->request->data[$Model]['unpaid_amount'] > 0) $this->request->data[$Model]['unpaid_amount'] = $this->request->data[$Model]['bill_amount'];
						
						// 請求日計算
						if($no > 0) $this->request->data[$Model]['date'] = date('Y-m-01',strtotime($this->request->data[$Model]['date'].' next month'));
						elseif(substr($this->request->data[$Model]['date'],0,7)!=substr($this->request->data[$Model]['advance_date'],0,7)) $this->request->data[$Model]['date'] = $this->request->data[$Model]['advance_date'];
						
						// データ保存
						$this->$Model->create();
						if($this->$Model->save($this->request->data)){
								
							// ID/顧客ID取得
							$id = $this->$Model->getInsertID();
							$member_id = $this->request->data[$Model]['member_id'];
							
							// データ準備（売上詳細）
							$v['sale_id'] = $id;
							$v['no'] = ++$no;
							
							// データ保存（欄）
							$sModel = $dModel;
							$this->$Model->$sModel->create();
							$this->$Model->$sModel->save($v);
							
						}
						
					}}
				}
				
				// アラート
				$this->Common->alertSet('前受金を一括で登録しました','success');
				
				// 在庫データ更新（最終出庫日／出庫数／在庫数／在庫額）
				$SQL = "UPDATE stocks AS S SET  ";
				$SQL.= "issue_date = (SELECT MAX(U.date) FROM ((SELECT MAX(purchases.date) AS date ,purchases.barcode AS barcode FROM purchases WHERE is_stock = 1 AND purchases.is_delete = 0 GROUP BY purchases.barcode) UNION  ";
				$SQL.= "(SELECT MAX(sales.date) AS date , sale_details.barcode AS barcode FROM sale_details LEFT JOIN sales ON sales.id = sale_details.sale_id WHERE sales.is_transfer = 0 AND sales.is_delete = 0 AND sale_details.is_transfer = 0 AND sale_details.is_delete = 0 GROUP BY sale_details.barcode)) AS U WHERE U.barcode = S.barcode), "; 
				$SQL.= "purchase_count = (SELECT SUM(purchases.quantity) AS quantity FROM purchases WHERE purchases.barcode = S.barcode AND purchases.is_stock = 1 AND purchases.is_delete = 0 GROUP BY purchases.barcode), ";
				$SQL.= "sale_count = (SELECT SUM(sale_details.quantity) AS quantity  FROM sale_details LEFT JOIN sales ON sales.id = sale_details.sale_id WHERE sale_details.barcode = S.barcode AND sales.is_transfer = 0 AND sales.is_delete = 0 AND sale_details.is_transfer = 0 AND sale_details.is_delete = 0 GROUP BY sale_details.barcode), "; 
				$SQL.= "issue_count = purchase_count + sale_count, ";
				$SQL.= "stock_count = (CASE WHEN is_intangible = 0 THEN quantity - issue_count + adjust_count ELSE 0 END) , stock_amount = stock_count * cost ";
				$SQL.= "WHERE S.is_delete = 0;";
				$this->$Model->query($SQL);
				
				// 売上データ更新（下代合計）
				$SQL = "UPDATE sales AS S SET cost_total = (SELECT IFNULL(SUM(ST.cost * SD.quantity),0) FROM sale_details AS SD LEFT JOIN stocks AS ST ON SD.barcode = ST.barcode WHERE SD.sale_id = S.id AND SD.barcode != '') WHERE S.is_delete = 0 AND S.is_transfer = 0;";
				$this->$Model->query($SQL);
				
				// リダイレクト
				if(!empty($_POST['submit']) && $_POST['submit']=="保存して顧客画面へ")	$this->redirect('/members/view/'.$member_id.'/');			// 顧客詳細画面へ
				else																	$this->redirect('/'.$this->params['controller'].'/');		// 一覧画面へ
				exit;
				
			// エラーがある場合
			}else{
				
				// アラート
				$this->Common->alertSet('入力内容が正しくありません。再度確認してください','danger');
				
				// 変数宣言
				$this->set(compact('errors'));
				
			}
			
		}elseif(empty($this->request->data)){
			
			// デフォルト値格納
			$schema = $this->$Model->schema();
			if(!empty($schema)){
				foreach($schema as $column => $schem){
					
					// 変数格納
					$this->request->data[$Model][$column] = $schem['default'];
					
				}
			}
			
			// 請求書設定取得
			$temp = $this->InvoiceSetting->read(null,1);
			if(!empty($temp)){
				foreach($temp['InvoiceSetting'] as $column => $value){
					if($column!="id") $this->request->data[$Model][$column] = $value;
				}
			}
			
			// 初期化
			$this->request->data[$Model]['date'] = date('Y-m-d');
			$this->request->data[$Model]['deposit_date'] = date('Y-m-t');
			$this->request->data[$Model]['advance_date'] = date('Y-m-01');
			$this->request->data[$Model]['reporter_id'] = $this->auth['id'];
			$this->request->data[$Model]['tax_rate'] = TAX_RATE;
			$this->request->data[$Model]['gamount'] = 0;
			$this->request->data[$Model]['period'] = 1;
			$this->request->data[$Model]['memo'] = date('Y年n月').'に前受';
			
			// 顧客管理からの場合
			if(!empty($_GET['member'])) $this->request->data[$Model]['member_id'] = $_GET['member'];
			
			// 初期値（売上詳細）
			$this->request->data[$dModel] = array();
			for($i=1;$i<=12;$i++) $this->request->data[$dModel] += array(
				mktime().$i => array(
					'name' => '営業コンサルティング',
					'quantity' => 1,
					'unit' => '式',
					'price' => '0',
					'total' => '0',
					'memo' => '',
				),
			);
			
		}
		
		// ダミー生成（売上詳細）
		$this->request->data[$dModel] = array(
			'dummy' => array(
				'name' => '営業コンサルティング',
				'quantity' => 1,
				'unit' => '式',
				'price' => '0',
				'total' => '0',
				'memo' => '',
			),
		) + $this->request->data[$dModel];
		
	}
	
	// 詳細
	public function view($id = null){
		
		// ページ設定
		$this->set('title',$this->tableName.'詳細');
		
		// モデル設定
		$Model = $this->modelClass;
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $id,						// ID
			)),
			'recursive' => 1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		//「削除する」ボタン押下時
		}elseif($this->Common->modeCheck('delete')){
			
			// 編集ロックチェック（管理者または編集ロックじゃない）
			if($this->auth['is_power']==1 || $detail[$Model]['date'] > $this->lockDate){
				
				// コードチェック
				if($_GET['code']==md5($this->params['controller'].$id)){
					
					// データ更新（削除フラグ）
					$this->$Model->id = $id;
					$this->$Model->saveField('is_delete',true);
					
					// 在庫データ更新（最終出庫日／出庫数／在庫数／在庫額）
					$SQL = "UPDATE stocks AS S SET  ";
					$SQL.= "issue_date = (SELECT MAX(U.date) FROM ((SELECT MAX(purchases.date) AS date ,purchases.barcode AS barcode FROM purchases WHERE is_stock = 1 AND purchases.is_delete = 0 GROUP BY purchases.barcode) UNION  ";
					$SQL.= "(SELECT MAX(sales.date) AS date , sale_details.barcode AS barcode FROM sale_details LEFT JOIN sales ON sales.id = sale_details.sale_id WHERE sales.is_transfer = 0 AND sales.is_delete = 0 AND sale_details.is_transfer = 0 AND sale_details.is_delete = 0 GROUP BY sale_details.barcode)) AS U WHERE U.barcode = S.barcode), "; 
					$SQL.= "purchase_count = (SELECT SUM(purchases.quantity) AS quantity FROM purchases WHERE purchases.barcode = S.barcode AND purchases.is_stock = 1 AND purchases.is_delete = 0 GROUP BY purchases.barcode), ";
					$SQL.= "sale_count = (SELECT SUM(sale_details.quantity) AS quantity  FROM sale_details LEFT JOIN sales ON sales.id = sale_details.sale_id WHERE sale_details.barcode = S.barcode AND sales.is_transfer = 0 AND sales.is_delete = 0 AND sale_details.is_transfer = 0 AND sale_details.is_delete = 0 GROUP BY sale_details.barcode), "; 
					$SQL.= "issue_count = purchase_count + sale_count, ";
					$SQL.= "stock_count = (CASE WHEN is_intangible = 0 THEN quantity - issue_count + adjust_count ELSE 0 END) , stock_amount = stock_count * cost ";
					$SQL.= "WHERE S.is_delete = 0;";
					$this->$Model->query($SQL);
					
					// アラート
					$this->Common->alertSet($this->tableName.(!empty($detail[$Model]['name']) ? '（'.$detail[$Model]['name'].'）' : '').'を削除しました','warning');
					
				}
				
				// リダイレクト（一覧画面へ）
				$this->redirect('/'.$this->params['controller'].'/');
				exit;
				
			}else{
				
				// アラート
				$this->Common->alertSet("この".$this->tableName.'は編集ロック中です','danger');
				
				// リダイレクト（詳細画面へ）
				$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
				exit;
				
			}
			
		}
		
		// ロック時編集履歴取得
		$params = array(
			'conditions' => array(							// 抽出条件
				'Lockedit.model' => $Model,
				'Lockedit.target_id' => $id,
				'Lockedit.is_delete' => 0,
			),
			'order' => array(								// 並び順
				'Lockedit.date' => 'DESC',
			),
			'recursive' => -1,								// 再帰階層
		);
		$temps = $this->Lockedit->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $k => $v) $detail['Lockedit'][$k] = $v['Lockedit'];
		}
		
		// 前後ID取得
		$params = array(
			'field' => 'id','value' => $id,					// ID指定
			'conditions' => $this->conditions,				// 抽出条件
			'fields' => array($Model.'.id'),				// 抽出項目
			'order' => $this->order,						// 並び順
			'recursive' => -1,								// 再帰階層
		);
		$neighbors = $this->$Model->find('neighbors',$params);
		
		// 変数宣言
		$this->set(compact('id','detail','neighbors'));
		
	}
	
	// 見積書出力
	public function estimate($id = null){
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// データ取得
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $id,						// ID
			)),
			'recursive' => 1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 変数移行
		$sale = $detail[$Model];
		$member = $detail['Member'];
		$list = $detail[$dModel];
		$detailCount = (!empty($list) ? count($list) : 0);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// ライブラリ読み込み／PDF設定
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		require_once(dirname(__FILE__).'/../../vendors/Common/Lib/tcpdf/tcpdf.php');
		require_once(dirname(__FILE__).'/../../vendors/Common/Lib/tcpdf/fpdi/autoload.php');
		
		// pdfのオブジェクト作成
		$tcpdf = new \setasign\Fpdi\Tcpdf\Fpdi("L", "mm", "A4", true, "UTF-8");
		
		// ヘッダーフッター無し
		$tcpdf->setPrintHeader(false);
		$tcpdf->setPrintFooter(false);
		
		// フォントカラー設定
		$tcpdf->SetTextColor(0, 0, 0);
		
		// 余白設定
		$tcpdf->setMargins(0,0,0);
		$tcpdf->setAutoPageBreak(0);
		
		// ページ追加
		$tcpdf->AddPage();
		
		// テンプレート指定
		if($detailCount <= 12)		$templateCode = '12';
		elseif($detailCount <= 25)	$templateCode = '25';
		else						$templateCode = '45';
		
		// テンプレート読み込み
		$tcpdf->setSourceFile(dirname(__FILE__)."/../../vendors/Common/Lib/tcpdf/template/estimate".$templateCode.".pdf");
		$tplIdx = $tcpdf->importPage(1);
		$tcpdf->useTemplate($tplIdx, null, null, null, null, true);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 基本情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 見積先
		$text = ($member['is_billing']==2 ? $member['company'] : $member['name']);
		$textLen = mb_strlen($text,"UTF-8");
		if($textLen >= 20)		$fontSize = 11;
		elseif($textLen >= 15)	$fontSize = 12;
		elseif($textLen >= 10)	$fontSize = 13;
		else					$fontSize = 14;
		$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
		$tcpdf->SetXY(25,45);
		$tcpdf->Cell(75,6.1,$text,0,0,'L','','',1);
		
		// 継承
		$tcpdf->SetFont("kozgopromedium", "B", 12);
		$tcpdf->Text(($member['is_billing']==2 ? 99 : 101.5), 45.5, ($member['is_billing']==2 ? "御中" : "様"));
		
		// 請求日
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->SetXY(155,38.8);
		$tcpdf->Cell(30,4,date('Y/n/j',strtotime($sale['date'])),0,0,'C');
		
		// 合計金額
		$tcpdf->SetFont("kozgopromedium", null, 13);
		$tcpdf->SetXY(50,65);
		$tcpdf->Cell(30,9,"¥".number_format($sale['bill_amount']),0,0,'R');
		
		// お支払期限
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->SetXY(44.75,76.5);
		$tcpdf->Cell(30,4,date('Y/n/j',strtotime($sale['deposit_date'])),0,0,'C');
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 郵便番号
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(116, 49, "〒".$sale['inhouse_zipcode']);
		
		// 住所
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(116, 54, $sale['inhouse_address']);
		
		// 会社名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(116, 59, $sale['inhouse_company']);
		
		// 代表者名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(116, 65, $sale['inhouse_name']);
		
		// 電話番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(116, 71, "TEL  ".$sale['inhouse_tel']);
		
		// 登録番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(116, 81, "登録番号：".$sale['inhouse_licenseno']);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報（社印）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		if(!empty($sale['inhouse_comseal'])){
			
			// ファイル取得
			$fileName = $sale['inhouse_comseal'];
			
			// 各値指定
			$year= substr($fileName,0,4);				// 年（ディレクトリ）
			$month = substr($fileName,4,2);				// 月（ディレクトリ）
			$day = substr($fileName,6,2);				// 日（ディレクトリ）
			$name = substr($fileName,15);						// ファイル名称
			$pathinfo = pathinfo($fileName);							// ファイル情報
			$extension = $pathinfo['extension'];						// 拡張子
			
			// ファイルパス指定
			$domain = str_replace("dev.","",$_SERVER['HTTP_HOST']);
			$filePath = dirname(__FILE__).'/../../vendors/upload/'.$year.'/'.$month.'/'.$day.'/'.$fileName;
			
			// 社印
			$tcpdf->Image($filePath,161,57,20,null,$extension);
//			$tcpdf->Image($image['url'],$x + ($width - ($imageinfo[0] /$imageinfo[1] * $height)) / 2,$y,null,$height,$extension);
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 売上詳細（テンプレート切り分け対応）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 詳細分ループ
		if(!empty($list)){
			$count = 0;
			foreach($list as $line){
				
				// フォントサイズ指定（テンプレート切り分け対応）
				if($templateCode==12)		$fontSize = 9;
				elseif($templateCode==25)	$fontSize = 8;	
				elseif($templateCode==45)	$fontSize = 6.75;
				
				// Y座標指定（テンプレート切り分け対応）
				if($templateCode==12)		$y = 96.8 + ($count*6.675);
				elseif($templateCode==25)	$y = 95.5 + ($count*4.47);
				elseif($templateCode==45)	$y = 92 + ($count*3.35);
				
				// 品名
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetDrawColor(255,0,0);
				$tcpdf->SetXY(31,$y - 1.2);
				if(!empty($line['barcode']) && !empty($this->choices[$dModel]['receipt'][$line['barcode']])){
					$name = (!empty($line['name']) ? $line['name'] : $this->choices[$dModel]['stock_name'][$this->choices[$dModel]['stock_id'][$line['barcode']]]).$this->choices[$dModel]['receipt'][$line['barcode']];
					$tcpdf->Cell(65,6.1,$name,0,0,'L','','',1);
				}else{
					$tcpdf->Cell(65,6.1,$line['name'],0,0,'L','','',1);
				}
				
				// 数量
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(104,$y - 1.25);
				$tcpdf->Cell(10,6.25,$line['quantity'],0,0,'C');
				
				// 呼称
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(111.5,$y - 1.25);
				$tcpdf->Cell(10,6.25,$line['unit'],0,0,'C');
				
				// 単価
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(121.5,$y - 1.25);
				$tcpdf->Cell(16.5,6.25,number_format($line['price']),0,0,'R');
				
				// 金額
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(131,$y - 1.25);
				$tcpdf->Cell(25,6.25,number_format($line['total']),0,0,'R');
				
				// 備考
				$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
				$tcpdf->SetXY(156.5,$y - 1.2);
				$tcpdf->Cell(28.5,6.1,$line['memo'],0,0,'L','','',1);
				
				// カウント
				$count++;
				
			}
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 合計金額／振込先（テンプレート切り分け対応）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// テンプレート切り分け対応
		if($templateCode==12){
			
			// フォントサイズ指定
			$fontSize = 9;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,176);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,183.5);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,191);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(131,198.5);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
		}elseif($templateCode==25){
			
			// フォントサイズ指定
			$fontSize = 8;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,207.5);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,214.5);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,222);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(131,229.75);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
		}elseif($templateCode==45){
			
			// フォントサイズ指定
			$fontSize = 6.75;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,241.5);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,245.75);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,249.9);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(131,254.15);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// PDF出力
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		//$pdf->Output(出力時のファイル名, 出力モード);
		$tcpdf->Output("output.pdf", "I");	// インライン表示
	//	$tcpdf->Output("output.pdf", "D");	// ダウンロード
		
	}
	
	// 請求書出力
	public function invoice($id = null){
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// データ取得
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $id,						// ID
			)),
			'recursive' => 1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 変数移行
		$sale = $detail[$Model];
		$member = $detail['Member'];
		$list = $detail[$dModel];
		$detailCount = (!empty($list) ? count($list) : 0);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// ライブラリ読み込み／PDF設定
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		require_once(dirname(__FILE__).'/../../vendors/Common/Lib/tcpdf/tcpdf.php');
		require_once(dirname(__FILE__).'/../../vendors/Common/Lib/tcpdf/fpdi/autoload.php');
		
		// pdfのオブジェクト作成
		$tcpdf = new \setasign\Fpdi\Tcpdf\Fpdi("L", "mm", "A4", true, "UTF-8");
		
		// ヘッダーフッター無し
		$tcpdf->setPrintHeader(false);
		$tcpdf->setPrintFooter(false);
		
		// フォントカラー設定
		$tcpdf->SetTextColor(0, 0, 0);
		
		// 余白設定
		$tcpdf->setMargins(0,0,0);
		$tcpdf->setAutoPageBreak(0);
		
		// ページ追加
		$tcpdf->AddPage();
		
		// テンプレート指定
		if($detailCount <= 12)		$templateCode = '12';
		elseif($detailCount <= 25)	$templateCode = '25';
		else						$templateCode = '45';
		
		// テンプレート読み込み
		$tcpdf->setSourceFile(dirname(__FILE__)."/../../vendors/Common/Lib/tcpdf/template/invoice".$templateCode.".pdf");
		$tplIdx = $tcpdf->importPage(1);
		$tcpdf->useTemplate($tplIdx, null, null, null, null, true);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 基本情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 請求先
		$text = ($member['is_billing']==2 ? $member['company'] : $member['name']);
		$textLen = mb_strlen($text,"UTF-8");
		if($textLen >= 20)		$fontSize = 11;
		elseif($textLen >= 15)	$fontSize = 12;
		elseif($textLen >= 10)	$fontSize = 13;
		else					$fontSize = 14;
		$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
		$tcpdf->SetXY(25,45);
		$tcpdf->Cell(75,6.1,$text,0,0,'L','','',1);
		
		// 継承
		$tcpdf->SetFont("kozgopromedium", "B", 12);
		$tcpdf->Text(($member['is_billing']==2 ? 99 : 101.5), 45.5, ($member['is_billing']==2 ? "御中" : "様"));
		
		// 請求日
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->SetXY(155,38.8);
		$tcpdf->Cell(30,4,date('Y/n/j',strtotime($sale['date'])),0,0,'C');
		
		// 合計金額
		$tcpdf->SetFont("kozgopromedium", null, 13);
		$tcpdf->SetXY(50,65);
		$tcpdf->Cell(30,9,"¥".number_format($sale['bill_amount']),0,0,'R');
		
		// お支払期限
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->SetXY(44.75,76.5);
		$tcpdf->Cell(30,4,date('Y/n/j',strtotime($sale['deposit_date'])),0,0,'C');
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 郵便番号
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(116, 49, "〒".$sale['inhouse_zipcode']);
		
		// 住所
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(116, 54, $sale['inhouse_address']);
		
		// 会社名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(116, 59, $sale['inhouse_company']);
		
		// 代表者名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(116, 65, $sale['inhouse_name']);
		
		// 電話番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(116, 71, "TEL  ".$sale['inhouse_tel']);
		
		// 登録番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(116, 81, "登録番号：".$sale['inhouse_licenseno']);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報（社印）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		if(!empty($sale['inhouse_comseal'])){
			
			// ファイル取得
			$fileName = $sale['inhouse_comseal'];
			
			// 各値指定
			$year= substr($fileName,0,4);				// 年（ディレクトリ）
			$month = substr($fileName,4,2);				// 月（ディレクトリ）
			$day = substr($fileName,6,2);				// 日（ディレクトリ）
			$name = substr($fileName,15);						// ファイル名称
			$pathinfo = pathinfo($fileName);							// ファイル情報
			$extension = $pathinfo['extension'];						// 拡張子
			
			// ファイルパス指定
			$domain = str_replace("dev.","",$_SERVER['HTTP_HOST']);
			$filePath = dirname(__FILE__).'/../../vendors/upload/'.$year.'/'.$month.'/'.$day.'/'.$fileName;
			
			// 社印
			$tcpdf->Image($filePath,161,57,20,null,$extension);
//			$tcpdf->Image($image['url'],$x + ($width - ($imageinfo[0] /$imageinfo[1] * $height)) / 2,$y,null,$height,$extension);
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 売上詳細（テンプレート切り分け対応）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 詳細分ループ
		if(!empty($list)){
			$count = 0;
			foreach($list as $line){
				
				// フォントサイズ指定（テンプレート切り分け対応）
				if($templateCode==12)		$fontSize = 9;
				elseif($templateCode==25)	$fontSize = 8;	
				elseif($templateCode==45)	$fontSize = 6.75;
				
				// Y座標指定（テンプレート切り分け対応）
				if($templateCode==12)		$y = 96.8 + ($count*6.675);
				elseif($templateCode==25)	$y = 95.5 + ($count*4.47);
				elseif($templateCode==45)	$y = 92 + ($count*3.35);
				
				// 品名
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetDrawColor(255,0,0);
				$tcpdf->SetXY(31,$y - 1.2);				
				if(!empty($line['barcode']) && !empty($this->choices[$dModel]['receipt'][$line['barcode']])){
					$name = (!empty($line['name']) ? $line['name'] : $this->choices[$dModel]['stock_name'][$this->choices[$dModel]['stock_id'][$line['barcode']]]).$this->choices[$dModel]['receipt'][$line['barcode']];
					$tcpdf->Cell(65,6.1,$name,0,0,'L','','',1);
				}else{
					$tcpdf->Cell(65,6.1,$line['name'],0,0,'L','','',1);
				}
				
				// 数量
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(104,$y - 1.25);
				$tcpdf->Cell(10,6.25,$line['quantity'],0,0,'C');
				
				// 呼称
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(111.5,$y - 1.25);
				$tcpdf->Cell(10,6.25,$line['unit'],0,0,'C');
				
				// 単価
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(121.5,$y - 1.25);
				$tcpdf->Cell(16.5,6.25,number_format($line['price']),0,0,'R');
				
				// 金額
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(131,$y - 1.25);
				$tcpdf->Cell(25,6.25,number_format($line['total']),0,0,'R');
				
				// 備考
				$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
				$tcpdf->SetXY(156.5,$y - 1.2);
				$tcpdf->Cell(28.5,6.1,$line['memo'],0,0,'L','','',1);
				
				// カウント
				$count++;
				
			}
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 合計金額／振込先（テンプレート切り分け対応）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// テンプレート切り分け対応
		if($templateCode==12){
			
			// フォントサイズ指定
			$fontSize = 9;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,176);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,183.5);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,191);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(131,198.5);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
			//////////////////////////////////////////////////////////////////////////
			
			// 枠線
			// $tcpdf->SetDrawColor(64,64,64);
			// $tcpdf->Rect(24,173,79,28);
			
			// タイトル
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->Text(26, 180, "【振込先】");
			
			// 銀行名／支店名
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->Text(26, 185, $sale['payee_bank'].'　'.$sale['payee_branch']);
			
			// 口座種別／口座番号／口座名称
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->Text(26, 190, $sale['payee_type'].'　口座番号'.$sale['payee_number'].'　'.$sale['payee_name']);
			
			// コメント
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
			$tcpdf->Text(26, 196, "恐れ入りますが、振込手数料はお客様にてご負担ください");
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
			$tcpdf->Text(26, 199.5, "ますようお願い申し上げます");
			
		}elseif($templateCode==25){
			
			// フォントサイズ指定
			$fontSize = 8;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,207.5);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,214.5);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,222);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(131,229.75);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
			//////////////////////////////////////////////////////////////////////////
			
			// タイトル
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->Text(26, 213, "【振込先】");
			
			// 銀行名／支店名
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->Text(26, 217, $sale['payee_bank'].'　'.$sale['payee_branch']);
			
			// 口座種別／口座番号／口座名称
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->Text(26, 220.5, $sale['payee_type'].'　口座番号'.$sale['payee_number'].'　'.$sale['payee_name']);
			
			// コメント
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
			$tcpdf->Text(26, 224.5, "恐れ入りますが、振込手数料はお客様にてご負担ください");
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
			$tcpdf->Text(26, 227, "ますようお願い申し上げます");
			
		}elseif($templateCode==45){
			
			// フォントサイズ指定
			$fontSize = 6.75;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,241.5);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,245.75);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(131,249.9);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(131,254.15);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
			//////////////////////////////////////////////////////////////////////////
			
			// タイトル
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 1);
			$tcpdf->Text(25, 247, "【振込先】");
			
			// 銀行名／支店名
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 1);
			$tcpdf->Text(25, 250, $sale['payee_bank'].'　'.$sale['payee_branch'].'　'.$sale['payee_type'].'　口座番号'.$sale['payee_number'].'　'.$sale['payee_name']);
			
			// コメント
			$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
			$tcpdf->Text(25, 253, "恐れ入りますが、振込手数料はお客様にてご負担くださいますようお願い申し上げます");
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// PDF出力
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		//$pdf->Output(出力時のファイル名, 出力モード);
		$tcpdf->Output("output.pdf", "I");	// インライン表示
	//	$tcpdf->Output("output.pdf", "D");	// ダウンロード
		
	}
	
	// 領収書出力
	public function receipt($id = null){
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// データ取得
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $id,						// ID
			)),
			'recursive' => 1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 変数移行
		$sale = $detail[$Model];
		$member = $detail['Member'];
		$list = $detail[$dModel];
		$detailCount = (!empty($list) ? count($list) : 0);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// ライブラリ読み込み／PDF設定
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		require_once(dirname(__FILE__).'/../../vendors/Common/Lib/tcpdf/tcpdf.php');
		require_once(dirname(__FILE__).'/../../vendors/Common/Lib/tcpdf/fpdi/autoload.php');
		
		// pdfのオブジェクト作成
		$tcpdf = new \setasign\Fpdi\Tcpdf\Fpdi("L", "mm", "A4", true, "UTF-8");
		
		// ヘッダーフッター無し
		$tcpdf->setPrintHeader(false);
		$tcpdf->setPrintFooter(false);
		
		// フォントカラー設定
		$tcpdf->SetTextColor(0, 0, 0);
		
		// 余白設定
		$tcpdf->setMargins(0,0,0);
		$tcpdf->setAutoPageBreak(0);
		
		// ページ追加
		$tcpdf->AddPage();
		
		// テンプレート指定
		if($detailCount <= 12)		$templateCode = '12';
		elseif($detailCount <= 25)	$templateCode = '25';
		else						$templateCode = '45';
		
		$detailCount = 12;
		
		// テンプレート読み込み
		$tcpdf->setSourceFile(dirname(__FILE__)."/../../vendors/Common/Lib/tcpdf/template/receipt".$templateCode.".pdf");
		$tplIdx = $tcpdf->importPage(1);
		$tcpdf->useTemplate($tplIdx, null, null, null, null, true);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 基本情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 請求先
		$text = ($member['is_billing']==2 ? $member['company'] : $member['name']);
		$textLen = mb_strlen($text,"UTF-8");
		if($textLen >= 20)		$fontSize = 11;
		elseif($textLen >= 15)	$fontSize = 12;
		elseif($textLen >= 10)	$fontSize = 13;
		else					$fontSize = 14;
		$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
		$tcpdf->SetXY(25,44);
		$tcpdf->Cell(72,6.1,$text,0,0,'L','','',1);
		
		// 継承
		$tcpdf->SetFont("kozgopromedium", "B", 12);
		$tcpdf->Text(($member['is_billing']==2 ? 98 : 99.5), 45, ($member['is_billing']==2 ? "御中" : "様"));
		
		// 入金日
		if(!empty($sale['payment_date'])){
			$tcpdf->SetFont("kozminproregular", null, 8);
			$tcpdf->SetXY(152,38.25);
			$tcpdf->Cell(30,4,date('Y/n/j',strtotime($sale['payment_date'])),0,0,'C');
		}
		
		// 合計金額
		$tcpdf->SetFont("kozgopromedium", null, 13);
		$tcpdf->SetXY(64,63.5);
		$tcpdf->Cell(30,9,"¥".number_format($sale['bill_amount']),0,0,'R');
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 郵便番号
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(115, 49, "〒".$sale['inhouse_zipcode']);
		
		// 住所
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(115, 54, $sale['inhouse_address']);
		
		// 会社名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(115, 59, $sale['inhouse_company']);
		
		// 代表者名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(115, 65, $sale['inhouse_name']);
		
		// 電話番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(115,71, "TEL  ".$sale['inhouse_tel']);
		
		// 登録番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(115, 80, "登録番号：".$sale['inhouse_licenseno']);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報（社印）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		if(!empty($sale['inhouse_comseal'])){
			
			// ファイル取得
			$fileName = $sale['inhouse_comseal'];
			
			// 各値指定
			$year= substr($fileName,0,4);				// 年（ディレクトリ）
			$month = substr($fileName,4,2);				// 月（ディレクトリ）
			$day = substr($fileName,6,2);				// 日（ディレクトリ）
			$name = substr($fileName,15);						// ファイル名称
			$pathinfo = pathinfo($fileName);							// ファイル情報
			$extension = $pathinfo['extension'];						// 拡張子
			
			// ファイルパス指定
			$domain = str_replace("dev.","",$_SERVER['HTTP_HOST']);
			$filePath = dirname(__FILE__).'/../../vendors/upload/'.$year.'/'.$month.'/'.$day.'/'.$fileName;
			
			// 社印
			$tcpdf->Image($filePath,158,57,20,null,$extension);
//			$tcpdf->Image($image['url'],$x + ($width - ($imageinfo[0] /$imageinfo[1] * $height)) / 2,$y,null,$height,$extension);
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 売上詳細（テンプレート切り分け対応）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 詳細分ループ
		if(!empty($list)){
			$count = 0;
			foreach($list as $line){
				
				// フォントサイズ指定（テンプレート切り分け対応）
				if($templateCode==12)		$fontSize = 9;
				elseif($templateCode==25)	$fontSize = 7.25;	
				elseif($templateCode==45)	$fontSize = 6;
				
				// Y座標指定（テンプレート切り分け対応）
				if($templateCode==12)		$y = 93.75 + ($count*6.5);
				elseif($templateCode==25)	$y = 90 + ($count*4.025);
				elseif($templateCode==45)	$y = 88.7 + ($count*2.95);
				
				// 品名
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetDrawColor(255,0,0);
				$tcpdf->SetXY(32,$y - 1.2);
				if(!empty($line['barcode']) && !empty($this->choices[$dModel]['receipt'][$line['barcode']])){
					$name = (!empty($line['name']) ? $line['name'] : $this->choices[$dModel]['stock_name'][$this->choices[$dModel]['stock_id'][$line['barcode']]]).$this->choices[$dModel]['receipt'][$line['barcode']];
					$tcpdf->Cell(65,6.1,$name,0,0,'L','','',1);
				}else{
					$tcpdf->Cell(65,6.1,$line['name'],0,0,'L','','',1);
				}
				
				// 数量
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(98.5,$y - 1.25);
				$tcpdf->Cell(10,6.25,$line['quantity'],0,0,'C');
				
				// 呼称
//				$tcpdf->SetFont("kozminproregular", null, 9);
//				$tcpdf->SetXY(95,$y - 1.25);
//				$tcpdf->Cell(10,6.25,$line['unit'],0,0,'C');
				
				// 単価
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(110.75,$y - 1.25);
				$tcpdf->Cell(16.5,6.25,number_format($line['price']),0,0,'R');
				
				// 金額
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetXY(124,$y - 1.25);
				$tcpdf->Cell(25,6.25,number_format($line['total']),0,0,'R');
				
				// 備考
				$tcpdf->SetFont("kozminproregular", null, $fontSize - 2);
				$tcpdf->SetXY(150,$y - 1.2);
				$tcpdf->Cell(33.25,6.1,$line['memo'],0,0,'L','','',1);
				
				// カウント
				$count++;
				
			}
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 合計金額／振込先（テンプレート切り分け対応）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// テンプレート切り分け対応
		if($templateCode==12){
			
			// フォントサイズ指定
			$fontSize = 9;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,170);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,177.25);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,184.5);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(124,191.75);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
			//////////////////////////////////////////////////////////////////////////
			
			// 支払方法
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,177);
			$tcpdf->Cell(25,6.25,'決済：'.$this->choices[$Model]['is_payment'][$sale['is_payment']],0,0,'R');
			
			// 合計金額
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,182);
			$tcpdf->Cell(25,6.25,'お預かり：'.number_format($sale['deposit_amount']).' 円',0,0,'R');
			
			// お釣り（0）
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,187);
			$tcpdf->Cell(25,6.25,'お釣り：'.number_format($sale['change_amount']).' 円',0,0,'R');
			
		}elseif($templateCode==25){
			
			// フォントサイズ指定
			$fontSize = 8;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,189.5);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,193.75);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,198);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(124,202.25);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
			//////////////////////////////////////////////////////////////////////////
			
			// 支払方法
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,193);
			$tcpdf->Cell(25,6.25,'決済：'.$this->choices[$Model]['is_payment'][$sale['is_payment']],0,0,'R');
			
			// 合計金額
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,196.5);
			$tcpdf->Cell(25,6.25,'お預かり：'.number_format($sale['deposit_amount']).' 円',0,0,'R');
			
			// お釣り（0）
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,200);
			$tcpdf->Cell(25,6.25,'お釣り：'.number_format($sale['change_amount']).' 円',0,0,'R');
			
		}elseif($templateCode==45){
			
			// フォントサイズ指定
			$fontSize = 6.5;
			
			// 小計
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,219.75);
			$tcpdf->Cell(25,6.5,number_format($sale['stotal']),0,0,'R');
			
			// 消費税率
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,222.75);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_rate']).'%',0,0,'R');
			
			// 消費税
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(124,225.75);
			$tcpdf->Cell(25,6.5,number_format($sale['tax_amount']),0,0,'R');
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(124,228.65);
			$tcpdf->Cell(25,6.5,number_format($sale['bill_amount']),0,0,'R');
			
			//////////////////////////////////////////////////////////////////////////
			
			// 支払方法
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,222);
			$tcpdf->Cell(25,6.25,'決済：'.$this->choices[$Model]['is_payment'][$sale['is_payment']],0,0,'R');
			
			// 合計金額
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,225);
			$tcpdf->Cell(25,6.25,'お預かり：'.number_format($sale['deposit_amount']).' 円',0,0,'R');
			
			// お釣り（0）
			$tcpdf->SetFont("kozminproregular", null, $fontSize);
			$tcpdf->SetXY(79,228);
			$tcpdf->Cell(25,6.25,'お釣り：'.number_format($sale['change_amount']).' 円',0,0,'R');
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// PDF出力
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		//$pdf->Output(出力時のファイル名, 出力モード);
		$tcpdf->Output("output.pdf", "I");	// インライン表示
	//	$tcpdf->Output("output.pdf", "D");	// ダウンロード
		
	}
	
	// 集計
	public function aggregate(){
		
		// ページ設定
		$this->set('title','担当者別'.$this->tableName.'集計');
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$conditions = array();
		$totalPrice = 0;
		
		// 外部ページからの指定
		if(!empty($_GET['mode']) && $_GET['mode']=="filter"){
			if(!empty($_GET['sdate'])) $this->request->data[$Model]['sdate'] = $_GET['sdate'];
			if(!empty($_GET['edate'])) $this->request->data[$Model]['edate'] = $_GET['edate'];
			$_GET['mode'] = 'search';
		}
		
		// 絞り込み
		if($this->Common->modeCheck('search')){
			
			// セッション保存
			$this->Session->write($this->viewPath.$this->action.'Data',$this->request->data);
			$this->Session->write($this->viewPath.$this->action.'Conditions',$conditions);
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/aggregate/');
			exit;
			
		// 条件クリア
		}elseif(!empty($_GET['mode']) && $_GET['mode']=="clear"){
			
			// セッション破棄
			$this->Session->delete($this->viewPath.$this->action.'Data');
			$this->Session->delete($this->viewPath.$this->action.'Conditions');
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/aggregate/');
			exit;
			
		// セッションチェック
		}elseif($this->Session->check($this->viewPath.$this->action.'Data')){
			
			// セッション取得
			$this->request->data = $this->Session->read($this->viewPath.$this->action.'Data');
			$conditions = $this->Session->read($this->viewPath.$this->action.'Conditions');
			
		}
		
		// 初期値
		if(empty($this->request->data[$Model]['sdate'])) $this->request->data[$Model]['sdate'] = date('Y-m-01');
		if(empty($this->request->data[$Model]['edate'])) $this->request->data[$Model]['edate'] = date('Y-m-d');
		
		// 変数移行
		$sdate = $this->request->data[$Model]['sdate'];
		$edate = $this->request->data[$Model]['edate'];
		
		// 担当者分ループ
		foreach(($this->choices['Common']['create_id'] + array(0=>'未登録','total'=>'合計')) as $charge_id => $charge_name){
			
			// 初期値
			$data[$charge_id][$Model] = array(
				'charge_id' => $charge_id,		// 担当者
				'count' => 0,					// 売上件数
				'stotal' => 0,					// 小計合計
				'sale_cost' => 0,				// 売上下代
				'purchase_cost' => 0,			// ﾘｰｽｱｲﾃﾑ下代
				'cost_total' => 0,				// 下代合計
				'profit_amount' => 0,			// 利益
				'cost_rate' => 0,				// 原価率
				'bill_amount' => 0,				// 総請求金額
			);
			
		}
		
		// 対象データ（クエリ生成）
		if(isset($this->request->data[$Model]['is_transfer']) && $this->request->data[$Model]['is_transfer']!=="") $QUERY = " AND S.is_transfer = ".$this->request->data[$Model]['is_transfer'];
		
		// リースアイテム下代取得
		$purchase_costs = array();
		$SQL = "SELECT M.charge_id , SUM(P.cost * P.quantity) AS cost_total FROM purchases AS P LEFT JOIN members AS M ON P.member_id = M.id ";
		$SQL.= "WHERE '$sdate' <= P.date AND P.date <= '$edate'AND P.is_delete = 0 GROUP BY M.charge_id;";
		$result = $this->$Model->query($SQL);
		if(!empty($result)){
			foreach($result as $v){
				
				// 変数格納
				$purchase_costs[$v['M']['charge_id']] = $v[0]['cost_total'];
				
			}
		}
		
		// データ取得
		$SQL = "SELECT S.charge_id , COUNT(S.id) AS count , SUM(S.cost_total) AS cost_total , SUM(S.stotal) AS stotal , SUM(S.bill_amount) AS bill_amount FROM sales AS S ";
		$SQL.= "WHERE '$sdate' <= S.date AND S.date <= '$edate' AND S.is_reflect = 1".(!empty($QUERY) ? $QUERY : '')." AND S.is_delete = 0 GROUP BY S.charge_id;";
		$result = $this->$Model->query($SQL);
		if(!empty($result)){
			foreach($result as $v){
				
				// 変数移行
				$line = $v[0];
				$charge_id = $v['S']['charge_id'];
				
				// 担当者がない場合
				if(empty($charge_id)) $data[$charge_id][$Model]['charge_id'] = $charge_id = 0;
				
				// リースアイテム下代格納
				$purchase_cost = (!empty($purchase_costs[$charge_id]) ? $purchase_costs[$charge_id] : 0);
				
				// 下代合算
				$sale_cost = $line['cost_total'];
				$cost_total = $purchase_cost + $sale_cost;
				
				// 変数格納
				$data[$charge_id][$Model]['count'] = $line['count'];																		// 売上件数
				$data[$charge_id][$Model]['stotal'] = $line['stotal'];																		// 小計合計
				$data[$charge_id][$Model]['sale_cost'] = $sale_cost;																		// 売上下代
				$data[$charge_id][$Model]['purchase_cost'] = $purchase_cost;																// ﾘｰｽｱｲﾃﾑ下代
				$data[$charge_id][$Model]['cost_total'] = $cost_total;																		// 下代合計
				$data[$charge_id][$Model]['profit_amount'] = $line['stotal'] - $cost_total;													// 利益
				$data[$charge_id][$Model]['cost_rate'] = (!empty($cost_total) ? $cost_total / $line['stotal'] * 100 : 0);					// 原価率
				$data[$charge_id][$Model]['bill_amount'] = $line['bill_amount'];															// 総請求金額
				
				// 総合計計算
				$data['total'][$Model]['count'] += $line['count'];																			// 売上件数
				$data['total'][$Model]['stotal'] += $line['stotal'];																		// 小計合計
				$data['total'][$Model]['sale_cost'] += $sale_cost;																			// 売上下代
				$data['total'][$Model]['purchase_cost'] += $purchase_cost;																	// ﾘｰｽｱｲﾃﾑ下代
				$data['total'][$Model]['cost_total'] += $cost_total;																		// 下代合計
				$data['total'][$Model]['profit_amount'] += $line['stotal'] - $cost_total;													// 利益
				$data['total'][$Model]['bill_amount'] += $line['bill_amount'];																// 総請求金額
				
			}
			
			// 原価率計算
			$data['total'][$Model]['cost_rate'] = (!empty($data['total'][$Model]['cost_total']) ? $data['total'][$Model]['cost_total'] / $data['total'][$Model]['stotal'] * 100 : 0);
			
		}
		
		
		// 変数宣言
		$this->set(compact('data','totalPrice'));
		
	}
	
	// 担当者別
	public function charge(){
		
		// ページ設定
		$this->set('title','担当者別'.$this->tableName.'詳細');
		
		// モデル設定
		$Model = $this->modelClass;
		$dModel = $Model.'Detail';
		
		// 初期値
		$conditions = array();
		$saleTotalPrice = $saleTotalCost = $purchaseTotalPrice = $purchaseTotalCost = 0;
		
		// 外部ページからの指定
		if(!empty($_GET['mode']) && $_GET['mode']=="filter"){
			if(!empty($_GET['sdate'])) $this->request->data[$Model]['sdate'] = $_GET['sdate'];
			if(!empty($_GET['edate'])) $this->request->data[$Model]['edate'] = $_GET['edate'];
			if(!empty($_GET['charge_id'])) $this->request->data[$Model]['charge_id'] = $_GET['charge_id'];
			if($_GET['is_transfer']!="none") $this->request->data[$Model]['is_transfer'] = $_GET['is_transfer'];
			$_GET['mode'] = 'search';
		}
		
		// 絞り込み
		if($this->Common->modeCheck('search')){
			
			// セッション保存
			$this->Session->write($this->viewPath.$this->action.'Data',$this->request->data);
			$this->Session->write($this->viewPath.$this->action.'Conditions',$conditions);
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/charge/');
			exit;
			
		// 条件クリア
		}elseif(!empty($_GET['mode']) && $_GET['mode']=="clear"){
			
			// セッション破棄
			$this->Session->delete($this->viewPath.$this->action.'Data');
			$this->Session->delete($this->viewPath.$this->action.'Conditions');
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/charge/');
			exit;
			
		// セッションチェック
		}elseif($this->Session->check($this->viewPath.$this->action.'Data')){
			
			// セッション取得
			$this->request->data = $this->Session->read($this->viewPath.$this->action.'Data');
			$conditions = $this->Session->read($this->viewPath.$this->action.'Conditions');
			
		}
		
		// 初期値
		if(empty($this->request->data[$Model]['sdate'])) $this->request->data[$Model]['sdate'] = date('Y-m-01');
		if(empty($this->request->data[$Model]['edate'])) $this->request->data[$Model]['edate'] = date('Y-m-d');
		if(empty($this->request->data[$Model]['charge_id'])) $this->request->data[$Model]['charge_id'] = 4;
		
		// 請求日（日付前後チェック）
		if(!empty($this->request->data[$Model]['sdate']) && !empty($this->request->data[$Model]['edate']) && $this->request->data[$Model]['sdate'] > $this->request->data[$Model]['edate']){ $temp = $this->request->data[$Model]['sdate']; $this->request->data[$Model]['sdate'] = $this->request->data[$Model]['edate']; $this->request->data[$Model]['edate'] = $temp; }
		if(!empty($this->request->data[$Model]['sdate'])) $conditions = am($conditions,array($Model.'.date >= ' => $this->request->data[$Model]['sdate']));
		if(!empty($this->request->data[$Model]['edate'])) $conditions = am($conditions,array($Model.'.date <= ' => $this->request->data[$Model]['edate']));
		
		// 担当ユーザー
		if(isset($this->request->data[$Model]['charge_id']) && $this->request->data[$Model]['charge_id']!=="") $conditions = am($conditions,array($Model.'.charge_id' => $this->request->data[$Model]['charge_id']));
		
		// 対象データ
		if(isset($this->request->data[$Model]['is_transfer']) && $this->request->data[$Model]['is_transfer']!=="") $conditions = am($conditions,array($Model.'.is_transfer' => $this->request->data[$Model]['is_transfer']));
		
		// 必須条件
		$conditions = am($conditions,array($Model.'.is_reflect' => 1));
		
		// データ取得（売上）
		$params = array(
			'conditions' => am($this->conditions,$conditions),		// 抽出条件
			'order' => $this->order,								// 並び順
			'limit' => 99999,										// 表示件数
			'maxLimit' => 99999,									// 上限件数
			'recursive' => 0,										// 再帰階層
			'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
		);
		$sale_data =  $this->$dModel->find('all',$params);
		if(!empty($sale_data)){
			foreach($sale_data as $k => $v){
				
				// バーコードがある場合
				$cost = (!empty($v[$dModel]['barcode']) && !empty($this->choices[$dModel]['stock_id'][$v[$dModel]['barcode']]) ? $this->choices[$dModel]['stock_cost'][$this->choices[$dModel]['stock_id'][$v[$dModel]['barcode']]] * $v[$dModel]['quantity'] : 0);
				$sale_data[$k][$dModel]['cost'] = $cost;
				$sale_data[$k][$dModel]['cost_rate'] = (!empty($v[$dModel]['total']) ? $cost / $v[$dModel]['total'] * 100 : 0);
				
				// 総合計計算
				$saleTotalPrice += $v[$dModel]['total'];
				$saleTotalCost += $cost;
				
			}
		}
		
		// データ取得（リースアイテム）
		if(!empty($conditions)){
			foreach($conditions as $k => $v){
				if(strpos($k,"charge_id")!==FALSE)	$conditionsR[str_replace($Model,'Member',$k)] = $v;
				elseif(strpos($k,"date")!==FALSE)	$conditionsR[str_replace($Model,'Purchase',$k)] = $v;
			}
		}
		$params = array(
			'conditions' => am(array('Purchase.is_delete' => 0),$conditionsR),		// 抽出条件
			'order' => array('Purchase.date' => 'ASC'),				// 並び順
			'limit' => 99999,										// 表示件数
			'maxLimit' => 99999,									// 上限件数
			'recursive' => 0,										// 再帰階層
			'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
		);
		$purchase_data =  $this->Purchase->find('all',$params);
		if(!empty($purchase_data)){
			foreach($purchase_data as $k => $v){
				
				// 下代合／利益率計計
				$cost = $v['Purchase']['cost'] * $v['Purchase']['quantity'];
				$purchase_data[$k]['Purchase']['cost_total'] = $cost;
				$purchase_data[$k]['Purchase']['cost_rate'] = (!empty($v['Purchase']['total']) ? $cost / $v['Purchase']['total'] * 100 : 0);
				
				// 総合計計算
				$purchaseTotalPrice += $v['Purchase']['total'];
				$purchaseTotalCost += $cost;
				
			}
		}
		
		// 変数宣言
		$this->set(compact('sale_data','saleTotalPrice','saleTotalCost','purchase_data','purchaseTotalPrice','purchaseTotalCost'));
		
	}
	
	// 年度別
	public function term(){
		
		// ページ設定
		$this->set('title','年度別'.$this->tableName.'集計');
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$conditions = $data = array();
		
		// 絞り込み
		if($this->Common->modeCheck('search')){
			
			// セッション保存
			$this->Session->write($this->viewPath.$this->action.'Data',$this->request->data);
			$this->Session->write($this->viewPath.$this->action.'Conditions',$conditions);
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/term/');
			exit;
			
		// 条件クリア
		}elseif(!empty($_GET['mode']) && $_GET['mode']=="clear"){
			
			// セッション破棄
			$this->Session->delete($this->viewPath.$this->action.'Data');
			$this->Session->delete($this->viewPath.$this->action.'Conditions');
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/term/');
			exit;
			
		// セッションチェック
		}elseif($this->Session->check($this->viewPath.$this->action.'Data')){
			
			// セッション取得
			$this->request->data = $this->Session->read($this->viewPath.$this->action.'Data');
			$conditions = $this->Session->read($this->viewPath.$this->action.'Conditions');
			
		}
		
		// 初期値
		if(empty($this->request->data[$Model]['year'])) $this->request->data[$Model]['year'] = date('Y',strtotime(date('Y-m-01').' -2 month'));
		
		// 変数移行
		$syear = $this->request->data[$Model]['year'];
		$eyear = date('Y',strtotime($syear.'-01-01 +1 year'));
		$sdate = $syear.'-03-01';
		$edate = $eyear.'-02-'.date('t',strtotime($eyear.'-02-01'));
		
		// 担当者分ループ
		foreach(($this->choices['Common']['create_id'] + array(0=>'未登録','total'=>'合計')) as $charge_id => $charge_name){
			
			// 初期値
			$data[$charge_id][$Model] = array(
				'charge_id' => $charge_id,		// 担当者
				'stotal' => 0,					// 小計合計
			);
			for($i=1;$i<=12;$i++) $data[$charge_id][$Model][(($i+1)%12)+1] = 0;
			
		}
		
		// 対象データ（クエリ生成）
		if(isset($this->request->data[$Model]['is_transfer']) && $this->request->data[$Model]['is_transfer']!=="") $QUERY = " AND S.is_transfer = ".$this->request->data[$Model]['is_transfer'];
		
		// データ取得
		$SQL = "SELECT S.charge_id , MONTH(S.date) AS month , COUNT(S.id) AS count , SUM(S.stotal) AS stotal FROM sales AS S ";
		$SQL.= "WHERE '$sdate' <= S.date AND S.date <= '$edate' AND S.is_reflect = 1".(!empty($QUERY) ? $QUERY : '')." AND S.is_delete = 0 GROUP BY S.charge_id , MONTH(S.date);";
		$result = $this->$Model->query($SQL);
		if(!empty($result)){
			foreach($result as $v){
				
				// 変数移行
				$line = $v[0];
				$charge_id = $v['S']['charge_id'];
				$month = $v[0]['month'];
				
				// 担当者がない場合
				if(empty($charge_id)) $data[$charge_id][$Model]['charge_id'] = $charge_id = 0;
				
				// 変数格納
				$data[$charge_id][$Model][$month] = $line['stotal']; 																		// 月別合計
				$data[$charge_id][$Model]['stotal'] += $line['stotal'];																		// 小計合計
				
				// 総合計計算
				$data['total'][$Model][$month] += $line['stotal']; 																			// 月別合計
				$data['total'][$Model]['stotal'] += $line['stotal'];																		// 小計合計
				
			}
		}
		
		// 変数宣言
		$this->set(compact('data'));
		
	}
	
	// CSVダウンロード
	public function csv(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$csv = '';
		$conditions = array();
		
		// セッションチェック／取得
		if($this->Session->check($this->viewPath.'indexConditions')) $conditions = $this->Session->read($this->viewPath.'indexConditions');
		
		// 顧客指定の場合
		if(!empty($_POST['member_id'])) $conditions = array($Model.'.member_id' => (int)$_POST['member_id']);
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,$conditions),		// 抽出条件
			'order' => $this->order,								// 並び順
			'recursive' => 1,										// 再帰階層
			'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
		);
		$data =  $this->$Model->find('all',$params);
		if(!empty($data)){
			
			// 項目名
			$fields = array(
				'売上ID',
				'請求書番号',
				'請求先',
				'売上種別',
				'入金期日',
				'請求日',
				'支払方法',
				'請求金額',
				'記入者',
				'担当者',
				'売上登録',
				'請求書送付',
				'お釣り',
				'お預かり',
				'入金消込',
				'入金日',
				'備考',
			);
			
			// 詳細表示対応
			if(!empty($this->request->data[$Model]['is_detail'])) $fields = am($fields,array(
				'No',			// 以下複数回入金
				'入金日',
				'入金金額',
				'入金消込',
				'備考',
				'売上詳細ID',
				'No',			// 以下売上詳細
				'商品コード',
				'バーコード',
				'品名',
				'価格',
				'数量',
				'金額',
				'備考',
			));
			foreach($fields as $k => $column){
				
				// カンマ区切り
				if(!empty($k)) $csv .= ",";
				
				// 項目名
				$csv .= $column;
				
			}
			
			// 改行
			$csv .= "\n";
			
			// 項目名「なし」の場合
			if(isset($this->request->data[$Model]['is_column']) && $this->request->data[$Model]['is_column']==0) $csv = '';
			
			// データ分ループ
			foreach($data as $k => $v){
				
				// 初期値
				$maxNo = 1;
				
				// 詳細表示対応
				if(!empty($this->request->data[$Model]['is_detail'])){
					
					// 入金回数が「1回]の場合
					if($v[$Model]['is_pay_plural']==0){
						$v[$Model.'Pay'][0] = array(
							'no' => 1,
							'date' => $v[$Model]['payment_date'],
							'price' => $v[$Model]['deposit_amount'],
							'is_deposit' => $v[$Model]['is_deposit'],
							'memo' => '',
						);
					}
					
					// 最大枝番号取得
					if(count($v[$Model.'Pay']) > $maxNo) $maxNo = count($v[$Model.'Pay']);
					if(count($v[$Model.'Detail']) > $maxNo) $maxNo = count($v[$Model.'Detail']);
					
				}
				
				// 枝番号分ループ
				for($no=0;$no<$maxNo;$no++){
					
					if(empty($this->request->data[$Model]['is_detail']) || $no==0){
						
						// 売上ID
						$fld = 'id';
						$csv .= '"'.$v[$Model][$fld].'",';
						
						// 請求書番号
						$fld = 'number';
						$csv .= '"'.$v[$Model][$fld].'",';
						
						// 請求先
						$fld = 'member_id';
						$csv .= '"'.(!empty($this->choices[$Model][$fld][$v[$Model][$fld]]) ? $this->choices[$Model][$fld][$v[$Model][$fld]] : '（該当顧客なし）').'",';
						
						// 売上種別
						$fld = 'is_type';
						$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
						
						// 入金期日
						$fld = 'deposit_date';
						$csv .= '"'.($v[$Model][$fld]!="0000-00-00" ? $v[$Model][$fld] : '').'",';
						
						// 請求日
						$fld = 'date';
						$csv .= '"'.$v[$Model][$fld].'",';
						
						// 支払方法
						$fld = 'is_payment';
						$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
						
						// 請求金額
						$fld = 'bill_amount';
						$csv .= '"'.$v[$Model][$fld].'",';
						
						// 記入者
						$fld = 'reporter_id';
						$csv .= '"'.(!empty($this->choices['Common']['create_id'][$v[$Model][$fld]]) ? $this->choices['Common']['create_id'][$v[$Model][$fld]] : '').'",';
						
						// 担当者
						$fld = 'charge_id';
						$csv .= '"'.(!empty($this->choices['Common']['create_id'][$v[$Model][$fld]]) ? $this->choices['Common']['create_id'][$v[$Model][$fld]] : '').'",';
						
						// 売上登録
						$fld = 'is_reflect';
						$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
						
						// 請求書送付
						$fld = 'is_send';
						$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
						
						// お釣り
						$fld = 'change_amount';
						$csv .= '"'.(!empty($v[$Model][$fld]) && !empty($v[$Model]['deposit_amount']) ? $v[$Model][$fld] : '').'",';
						
						// お預かり
						$fld = 'deposit_amount';
						$csv .= '"'.$v[$Model][$fld].'",';
						
						// 入金消込
						$fld = 'is_deposit';
						$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
						
						// 入金日
						$fld = 'payment_date';
						$csv .= '"'.$v[$Model][$fld].'",';
						
						// 備考
						$fld = 'memo';
						$csv .= '"'.(!empty($v[$Model][$fld]) ? str_replace("\r\n"," ",$v[$Model][$fld]) : '').'",';
						
					}else{
						
						// 空白格納
						$csv .= ",,,,,,,,,,,,,,,,,";
						
					}
					
					// 詳細表示対応
					if(!empty($this->request->data[$Model]['is_detail'])){
						
						// 入金履歴チェック
						if(!empty($v[$Model.'Pay']) && !empty($v[$Model.'Pay'][$no])){
							
							// 変数移行
							$detail = $v[$Model.'Pay'][$no];
							
							// No
							$fld = 'no';
							$csv .= '"'.$detail[$fld].'",';
							
							// 入金日
							$fld = 'date';
							$csv .= '"'.$detail[$fld].'",';
							
							// 入金金額
							$fld = 'price';
							$csv .= '"'.$detail[$fld].'",';
							
							// 入金消込
							$fld = 'is_deposit';
							$csv .= '"'.$this->choices[$Model][$fld][$detail[$fld]].'",';
							
							// 備考
							$fld = 'memo';
							$csv .= '"'.(!empty($detail[$fld]) ? str_replace("\r\n"," ",$detail[$fld]) : '').'",';
							
						}else{
							
							// 空白格納
							$csv .= ",,,,,";
							
						}
						
						// 売上詳細チェック
						if(!empty($v[$Model.'Detail']) && !empty($v[$Model.'Detail'][$no])){
							
							// 変数移行
							$detail = $v[$Model.'Detail'][$no];
							
							// 売上詳細ID
							$fld = 'id';
							$csv .= '"'.$detail[$fld].'",';
							
							// No
							$fld = 'no';
							$csv .= '"'.$detail[$fld].'",';
							
							// 商品コード
							$fld = 'code';
							$csv .= '"'.$detail[$fld].'",';
							
							// バーコード
							$fld = 'barcode';
							$csv .= '"'.$detail[$fld].'",';
							
							// 品名
							$fld = 'name';
							$csv .= '"'.$detail[$fld].'",';
							
							// 価格（税込み）
							$fld = 'price';
							$csv .= '"'.($detail[$fld] * (($v[$Model]['tax_rate']+100)/100)).'",';
							
							// 数量
							$fld = 'quantity';
							$csv .= '"'.$detail[$fld].'",';
							
							// 金額（税込み）
							$fld = 'total';
							$csv .= '"'.($detail[$fld] * (($v[$Model]['tax_rate']+100)/100)).'",';
							
							// 備考
							$fld = 'memo';
							$csv .= '"'.(!empty($detail[$fld]) ? str_replace("\r\n"," ",$detail[$fld]) : '').'",';
							
						}else{
							
							// 空白格納
							$csv .= ",,,,,,,,,";
							
						}
						
					}
					
					// 改行
					$csv .= "\n";
					
				}
				
			}
			
			// 最終改行除去
			$csv = rtrim($csv,"\n");
			
			// 文字コード変換（SJISへ）
			$csv = mb_convert_encoding($csv, 'SJIS','UTF-8');
			
			// MIMEタイプの設定
			header('Content-Type: application/octet-stream');
			
			// ファイル名の表示
			header('Content-Disposition: attachment; filename='.$Model.date('YmdHis').'.csv');
			
			// データの出力
			echo($csv);
			
			// 強制終了
			Configure::write('debug',0); exit;
			
		}else{
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
	}
	
	// 共通アクション呼び出し
	public function common($action = null){
		
		// 初期値
		$options = array();
		
		// アクション呼び出し
		if(!empty($action)) $this->$action($options);
		
	}
	
}

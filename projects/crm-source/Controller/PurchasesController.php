<?php
App::uses('AppController', 'Controller');
App::uses('Model', 'Model');
class PurchasesController extends AppController{
	
	// 設定
	public $uses = array('Purchase','Member','Stock');	// 使用モデル
	public $conditions = array();						// 基本抽出条件
	public $order = array();							// 基本並び順
	
	// beforeFilter
	public function beforeFilter(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 親へ
		parent::beforeFilter();
		
		// 短縮用変数宣言
		$this->tableName = $this->tables[$this->params['controller']];
		
		// 基本抽出条件指定
		$this->conditions = array($Model.'.is_delete' => 0);
		
		// 基本並び順指定
		$this->order = array($Model.'.date' => 'DESC');
		
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
			foreach($temps as $k => $v) $this->choices[$Model]['member_id'][$v['Member']['id']] = $v['Member']['name'].(!empty($v['Member']['kana']) ? '('.$v['Member']['kana'].')' : '').' / ID:'.$v['Member']['id'];
		}
		
		// 顧客契約一覧取得
		$params = array(
			'conditions' => array(
				'MemberContract.is_delete' => 0,		// 削除フラグ（0:通常）
			),
			'order' => array(
				'MemberContract.member_id' => 'ASC',	
				'MemberContract.no' => 'ASC',	
			),
			'recursive' => -1,
		);
		$temps = $this->Member->MemberContract->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $v) $this->choices[$Model]['contract_id'][$v['MemberContract']['member_id']][$v['MemberContract']['id']] = $v['MemberContract']['name'];
		}
		
		// 在庫一覧取得
		if(!$this->Session->check('StockList')){
			$params = array(
				'conditions' => array('Stock.is_delete' => 0),	// 削除フラグ（0:通常）
				'order' => array('Stock.code' => 'ASC'),
				'recursive' => 0,
			);
			$temps = $this->Stock->find('all',$params);
			$this->Session->write('StockList',$temps);
		}else $temps = $this->Session->read('StockList');
		if(!empty($temps)){
			foreach($temps as $k => $v){
				$this->choices[$Model]['barcode'][$v['Stock']['barcode']] = $v['Stock']['barcode'];			// バーコード
				$this->choices[$Model]['code'][$v['Stock']['barcode']] = $v['Stock']['code']				// 商品コード（商品コード）
					.(!empty($v['Stock']['name']) ? '/'.$v['Stock']['name'] : '')							// ＋品名
					.(!empty($v['Color']['name']) ? '/'.$v['Color']['name'] : '')							// ＋カラー
					.(!empty($v['Size']['name']) ? '/'.$v['Size']['name'] : '');							// ＋サイズ
				$this->choices[$Model]['stock_id'][$v['Stock']['barcode']] = $v['Stock']['id'];				// 在庫ID
				$this->choices[$Model]['stock_code'][$v['Stock']['id']] = $v['Stock']['code'];				// 在庫ID別:商品コード
				$this->choices[$Model]['stock_name'][$v['Stock']['id']] = $v['Stock']['name'];				// 在庫ID別:品名
				$this->choices[$Model]['stock_brand'][$v['Stock']['id']] = $v['Brand']['name'];				// 在庫ID別:ブランド
				$this->choices[$Model]['stock_color'][$v['Stock']['id']] = $v['Color']['name'];				// 在庫ID別:カラー
				$this->choices[$Model]['stock_size'][$v['Stock']['id']] = $v['Size']['name'];				// 在庫ID別:サイズ
				$this->choices[$Model]['stock_supplier'][$v['Stock']['id']] = $v['Supplier']['name'];		// 在庫ID別:仕入れｓ会
				$this->choices[$Model]['stock_cost'][$v['Stock']['id']] = $v['Stock']['cost'];				// 在庫ID別:下代
				$this->choices[$Model]['stock_price'][$v['Stock']['id']] = $v['Stock']['price'];			// 在庫ID別:上代
			}
		}
		
	}
	
	// 一覧
	public function check(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.contract_id IS NULL',
				$Model.'.temp != ""',
			)),
			'recursive' => -1,								// 再帰階層
		);
		$data = $this->$Model->find('all',$params);
		if(!empty($data)){
			foreach($data as $k => $v){
				
				$purchase_id = $v[$Model]['id'];
				$member_id = $v[$Model]['member_id'];
				$temp = $v[$Model]['temp'];
				$contract_id = array_search($temp.'全期',$this->choices[$Model]['contract_id'][$member_id]);
				
				if(!empty($contract_id)){
					$this->$Model->id = $purchase_id;
					$this->$Model->saveField('contract_id',$contract_id);
				}
				
				echo '<pre>';
				var_dump($purchase_id);
				var_dump($contract_id);
				echo '</pre>';
				
			}
		}
		exit;
		
	}
	
	// 一覧
	public function sample(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		$this->$Model->query("TRUNCATE purchases");
		
		$date = '2020-01-01';
		for($i=0;$i<=200;$i++){
			
			$date = date('Y-m-d',strtotime($date.' +'.rand(0,12).'day'));
			
			$price = rand(2,1000);
			$quantity = ($price <= 500 ? ($price <= 100 ? rand(1,5) : rand(1,3)) : 1);
			$price = $price * 500;
			$is_selection = rand(0,10) ? 1 : 0;
			$total = $price * $quantity + ($is_selection ? $price * $quantity * 0.15 : 0);
			$purchase = array(
				'member_id' => 1,
				'date' => $date,
				'price' => $price,
				'quantity' => $quantity,
				'is_selection' => $is_selection,
				'total' => $total,
				'item_name' => 'サンプル商品',
				'item_number' => 'sample',
				'brand_name' => 'サンプルブランド'
			);
			
			$this->$Model->create();
			$this->$Model->save($purchase);
			
		}
		
		exit;
		
	}
	
	// 一覧
	public function index(){
		
		// ページ設定
		$this->set('title',$this->tableName.'一覧');
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$conditions = array();
		
		// 日付一括変更
		if($this->Common->modeCheck('date_change')){
			
			// 初期値
			$count = 0;
			
			// セッションチェック
			if($this->Session->check($this->viewPath.$this->action.'Data')) $conditions = $this->Session->read($this->viewPath.$this->action.'Conditions');
			
			// 変数チェック
			if(!empty($this->request->data[$Model]['new_date'])){
				
				// 変数移行
				$new_date = $this->request->data[$Model]['new_date'];
				
				// 対象リースアイテム取得
				$params = array(
					'conditions' => am($this->conditions,$conditions),		// 抽出条件
					'fields' => array($Model.'.date'),						// 抽出項目
					'recursive' => 0,										// 再帰階層
				);
				$purchases = $this->$Model->find('list',$params);
				if(!empty($purchases) && count($purchases) <= 100){
					foreach($purchases as $purchase_id => $old_date){
						
						// 日付チェック
						if($new_date!=$old_date){
							
							// データ更新（リースアイテム）
							$this->$Model->id = $purchase_id;
							$this->$Model->saveField('date',$new_date);
							
							// カウント
							$count++;
							
						}
						
					}
				}
				
			}
			
			// アラート
			if($count > 0) $this->Common->alertSet('「 '.$count.' 」件の'.$this->tableName.'の日付を「'.$new_date.'」に変更しました。','success');
			
			// リダイレクト
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		// 絞り込み
		}elseif($this->Common->modeCheck('search')){
			
			// キーワード検索
			if(!empty($this->request->data[$Model]['keyword'])){
				$keywords = $this->Common->explodeEx(' ',$this->request->data[$Model]['keyword']);
				foreach($keywords as $keyword) $conditions[] = am($conditions,array('OR' => array(
					$Model.'.item_name LIKE "%'.$keyword.'%"',		// 品名
					$Model.'.item_number LIKE "%'.$keyword.'%"',	// 商品番号
					$Model.'.brand_name LIKE "%'.$keyword.'%"',		// ブランド
					'Member.name LIKE "%'.$keyword.'%"',			// お名前
					'Member.kana LIKE "%'.$keyword.'%"',			// フリガナ
				)));
			}
			
			// 日付（日付前後チェック）
			if(!empty($this->request->data[$Model]['sdate']) && !empty($this->request->data[$Model]['edate']) && $this->request->data[$Model]['sdate'] > $this->request->data[$Model]['edate']){ $temp = $this->request->data[$Model]['sdate']; $this->request->data[$Model]['sdate'] = $this->request->data[$Model]['edate']; $this->request->data[$Model]['edate'] = $temp; }
			if(!empty($this->request->data[$Model]['sdate'])) $conditions = am($conditions,array($Model.'.date >= ' => $this->request->data[$Model]['sdate']));
			if(!empty($this->request->data[$Model]['edate'])) $conditions = am($conditions,array($Model.'.date <= ' => $this->request->data[$Model]['edate']));
			
			// 在庫対象
			if(isset($this->request->data[$Model]['is_stock']) && $this->request->data[$Model]['is_stock']!=="") $conditions = am($conditions,array($Model.'.is_stock' => $this->request->data[$Model]['is_stock']));
			
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
		if(!empty($data)){
			foreach($data as $k => $v){
				
				// 在庫商品の場合
				if($v[$Model]['is_stock']==1 && !empty($v[$Model]['barcode'])){
					
					// 在庫ID取得
					$stock_id = $this->choices[$Model]['stock_id'][$v[$Model]['barcode']];
					
					// 各名称反映
					$data[$k][$Model]['stock_id'] = $stock_id;
					$data[$k][$Model]['brand_name'] = $this->choices[$Model]['stock_brand'][$stock_id];
					$data[$k][$Model]['color'] = $this->choices[$Model]['stock_color'][$stock_id];
					$data[$k][$Model]['size'] = $this->choices[$Model]['stock_size'][$stock_id];
					$data[$k][$Model]['bs'] = $this->choices[$Model]['stock_supplier'][$stock_id];
					
				}
				
			}
		}
		
		// 変数宣言
		$this->set(compact('data'));
		
	}
	
	// 登録/編集
	public function edit($id = null){
		
		// ページ設定
		$this->set('title',$this->tableName.(!empty($id) ? '編集' : '新規登録'));
		
		// モデル設定
		$Model = $this->modelClass;
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// エラーがない場合
			if(empty($errors)){
				
				// データ準備（商品コード反映）
				if(!empty($this->request->data[$Model]['code'])) $this->request->data[$Model]['code'] = $this->choices[$Model]['stock_code'][$this->choices[$Model]['stock_id'][$this->request->data[$Model]['code']]];
				
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
					
					// 顧客データ更新
					$this->MemberDataUpdate($member_id);
					
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
					
					// リダイレクト
					if(!empty($_POST['submit']) && $_POST['submit']=="保存して顧客画面へ")			$this->redirect('/members/view/'.$member_id.'/');							// 顧客詳細画面へ
					elseif(!empty($_POST['submit']) && $_POST['submit']=="保存して続けて登録する")	$this->redirect('/'.$this->params['controller'].'/add/?continue='.$id);	// 登録画面へ
					elseif(!empty($_POST['submit']) && $_POST['submit']=="保存して在庫を登録する")	$this->redirect('/stocks/add/?purchase='.$id);								// 在庫化(登録)へ
					else																			$this->redirect('/'.$this->params['controller'].'/');						// 一覧画面へ
					exit;
					
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
					'recursive' => -1,								// 再帰階層
				);
				$this->request->data = $this->$Model->find('first',$params);
				if(empty($this->request->data)){
					
					// リダイレクト（一覧画面へ）
					$this->redirect('/'.$this->params['controller'].'/');
					exit;
					
				}
				
				// 在庫商品の場合
				if($this->request->data[$Model]['is_stock']==1 && !empty($this->request->data[$Model]['barcode'])){
					
					// 在庫ID取得
					$stock_id = $this->choices[$Model]['stock_id'][$this->request->data[$Model]['barcode']];
					
					// 各名称反映
					$this->request->data[$Model]['stock_id'] = $stock_id;
					$this->request->data[$Model]['brand_name'] = $this->choices[$Model]['stock_brand'][$stock_id];
					$this->request->data[$Model]['color'] = $this->choices[$Model]['stock_color'][$stock_id];
					$this->request->data[$Model]['size'] = $this->choices[$Model]['stock_size'][$stock_id];
					$this->request->data[$Model]['bs'] = $this->choices[$Model]['stock_supplier'][$stock_id];
					
					// 商品コード（バーコード対応）
					$this->request->data[$Model]['code'] = $this->request->data[$Model]['barcode'];
					
				}
				
			// 続けて登録
			}elseif(!empty($_GET['continue'])){
				
				// データ取得
				$params = array(
					'conditions' => am($this->conditions,array(		// 抽出条件
						$Model . '.id' => $_GET['continue'],		// ID
					)),
					'recursive' => -1,								// 再帰階層
				);
				$before = $this->$Model->find('first',$params);
				if(empty($before)){
					
					// リダイレクト（一覧画面へ）
					$this->redirect('/'.$this->params['controller'].'/');
					exit;
					
				}
				
				// 初期化
				$this->request->data[$Model]['is_stock'] = 1;
				$this->request->data[$Model]['cost'] = $this->request->data[$Model]['price'] = '';
				$this->request->data[$Model]['member_id'] = $before[$Model]['member_id'];
				$this->request->data[$Model]['date'] = $before[$Model]['date'];
				$this->request->data[$Model]['is_selection'] = 0;
				$this->request->data[$Model]['total'] = $this->request->data[$Model]['profit_amount'] = $this->request->data[$Model]['profit_rate'] = 0;
				
			// 新規登録時
			}else{
				
				// デフォルト値格納
				$schema = $this->$Model->schema();
				if(!empty($schema)){
					foreach($schema as $fld => $schem){
						
						// 変数格納
						$this->request->data[$Model][$fld] = $schem['default'];
						
					}
				}
				
				// 初期化
				$this->request->data[$Model]['cost'] = $this->request->data[$Model]['price'] = '';
				$this->request->data[$Model]['date'] = date('Y-m-d');
				
				// 顧客管理からの場合
				if(!empty($_GET['member'])) $this->request->data[$Model]['member_id'] = $_GET['member'];
				
			}
			
		}
		
		// 変数宣言
		$this->set(compact('id'));
		
	}
	
	// 一括登録
	public function lump(){
		
		// ページ設定
		$this->set('title',$this->tableName.'一括登録');
		
		// モデル設定
		$Model = $this->modelClass;
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// 初期値
			$count = 0;
			
			// データ分ループ
			if(!empty($this->request->data['Lump'])){
				foreach($this->request->data['Lump'] as $purchase){
					
					// 品名がある場合
					if(!empty($purchase['item_name'])){
						
						// データ準備（商品コード反映）
						if(!empty($purchase['code'])) $purchase['code'] = $this->choices[$Model]['stock_code'][$this->choices[$Model]['stock_id'][$purchase['code']]];
						
						// データ準備
						$purchase['member_id'] = $this->request->data[$Model]['member_id'];
						$purchase['date'] = $this->request->data[$Model]['date'];
						$purchase['contract_id'] = $this->request->data[$Model]['contract_id'];
						
						// データ保存
						$this->$Model->create();
						$this->$Model->save($purchase);
						
						// カウント
						$count++;
						
					}
					
				}
			}
			
			// 1件以上登録がある場合
			if($count >= 1){
				
				// アラート
				$this->Common->alertSet('新しい'.$this->tableName.'を「 '.$count.'件 」一括登録しました','success');
				
				// 顧客ID取得
				$member_id = $this->request->data[$Model]['member_id'];
				
				// 顧客データ更新
				$this->MemberDataUpdate($member_id);
				
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
				
				// リダイレクト
				if(!empty($_POST['submit']) && $_POST['submit']=="保存して顧客画面へ")			$this->redirect('/members/view/'.$member_id.'/');							// 顧客詳細画面へ
				else																			$this->redirect('/'.$this->params['controller'].'/');						// 一覧画面へ
				exit;
				
			}else{
				
				// アラート
				$this->Common->alertSet('入力内容が正しくありません。再度確認してください','danger');
				
			}
			
		}elseif(empty($this->request->data)){
			
			// デフォルト値格納
			$schema = $this->$Model->schema();
			if(!empty($schema)){
				foreach($schema as $fld => $schem){
					
					// 変数格納
					$this->request->data[$Model][$fld] = $schem['default'];
					for($no=1;$no<=10;$no++) $this->request->data['Lump'][$no][$fld] = $schem['default'];
					
				}
			}
			
			// 初期化
			$this->request->data[$Model]['date'] = date('Y-m-d');
			for($no=1;$no<=10;$no++) $this->request->data[$Model]['is_stock'] = 1;
			for($no=1;$no<=10;$no++) $this->request->data['Lump'][$no]['cost'] = $this->request->data['Lump'][$no]['price'] = '';
			for($no=1;$no<=10;$no++) $this->request->data[$Model]['total'] = $this->request->data[$Model]['profit_amount'] = $this->request->data[$Model]['profit_rate'] = 0;
			
		}
		
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
				$Model . '.id' => $id,						// ID
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
				
				// 顧客データ更新
				$this->MemberDataUpdate($detail[$Model]['member_id']);
				
				// アラート
				$this->Common->alertSet($this->tableName.(!empty($detail[$Model]['name']) ? '（'.$detail[$Model]['name'].'）' : '').'を削除しました','warning');
				
			}
			
			// // リダイレクト（一覧画面へ）
			// $this->redirect('/'.$this->params['controller'].'/');
			// exit;
			
			// リダイレクト（顧客詳細画面へ）
			$this->redirect('/members/view/'.$detail[$Model]['member_id'].'/');
			exit;
			
		}
		
		// 在庫商品の場合
		if($detail[$Model]['is_stock']==1 && !empty($detail[$Model]['barcode'])){
			
			// 在庫ID取得
			$stock_id = $this->choices[$Model]['stock_id'][$detail[$Model]['barcode']];
			
			// 各名称反映
			$detail[$Model]['stock_id'] = $stock_id;
			$detail[$Model]['brand_name'] = $this->choices[$Model]['stock_brand'][$stock_id];
			$detail[$Model]['color'] = $this->choices[$Model]['stock_color'][$stock_id];
			$detail[$Model]['size'] = $this->choices[$Model]['stock_size'][$stock_id];
			$detail[$Model]['bs'] = $this->choices[$Model]['stock_supplier'][$stock_id];
			
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
			'recursive' => 0,										// 再帰階層
			'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
		);
		$data =  $this->$Model->find('all',$params);
		if(!empty($data)){
			
			// 項目名
			$fields = array(
				'日付',
				'対象契約',
				'対象顧客（お名前）',
				'在庫対象',
				'ブランド',
				'品名',
				'サイズ',
				'カラー',
				'下代',
				'上代',
				'点数',
				'15%',
				'小計',
				'利益率',
				'利益額',
			);
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
				
				// 在庫商品の場合
				if($v[$Model]['is_stock']==1 && !empty($v[$Model]['barcode'])){
					
					// 在庫ID取得
					$stock_id = $this->choices[$Model]['stock_id'][$v[$Model]['barcode']];
					
					// 各名称反映
					$v[$Model]['stock_id'] = $stock_id;
					$v[$Model]['brand_name'] = $this->choices[$Model]['stock_brand'][$stock_id];
					$v[$Model]['color'] = $this->choices[$Model]['stock_color'][$stock_id];
					$v[$Model]['size'] = $this->choices[$Model]['stock_size'][$stock_id];
					$v[$Model]['bs'] = $this->choices[$Model]['stock_supplier'][$stock_id];
					
				}
				
				///////////////////////////////////////////////////////////////////////////////////////////////////
				
				// 日付
				$fld = 'date';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 対象契約
				$fld = 'contract_id';
				$csv .= '"'.(!empty($this->choices[$Model][$fld][$v[$Model]['member_id']][$v[$Model][$fld]]) ? $this->choices[$Model][$fld][$v[$Model]['member_id']][$v[$Model][$fld]] : '').'",';
				
				// 対象顧客（お名前）
				$fld = 'name'; $fld2 = 'kana';
				$csv .= '"'.$v['Member'][$fld].'（'.$v['Member'][$fld2].'）",';
				
				// 在庫対象
				$fld = 'is_stock';
				$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
				
				// ブランド
				$fld = 'brand_name';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 品名
				$fld = 'item_name';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// サイズ
				$fld = 'size';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// カラー
				$fld = 'color';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 下代
				$fld = 'cost';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 上代
				$fld = 'price';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 点数
				$fld = 'quantity';
				$csv .= '"'.$v[$Model][$fld].'ヶ",';
				
				// 15%
				$fld = 'is_selection';
				$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
				
				// 小計
				$fld = 'total';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 利益率
				$fld = 'profit_rate';
				$csv .= '"'.number_format($v[$Model][$fld]*100).'%",';
				
				// 利益額
				$fld = 'profit_amount';
				$csv .= '"'.$v[$Model][$fld].'",';
				
				// 改行
				$csv .= "\n";
				
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

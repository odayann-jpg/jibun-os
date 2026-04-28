<?php
App::uses('Controller', 'Controller');
class AppController extends Controller{
	
	// 読み込み
	public $components = array('Session','File','StartUp','Common','Csv');
	public $uses = array('Upload','User','Member');
	public $helpers = array(
		'Form' => array('className' => 'RemakeForm'),
		'Html' => array('className' => 'RemakeHtml')
	);
	
	// 初期値
	public $needAuth = true;
	public $auth = array();
	public $choices = array();
	public $colors = array();
	public $tables = array();
	public $comments = array();
	
	// beforeFilter
	public function beforeFilter(){
		
		// ヘッダー設定
		$this->disableCache();
		date_default_timezone_set('Asia/Tokyo');
		
		// 認証セッションチェック
		if($this->Session->check('auth')){
			
			// セッション取得（認証者情報）
			$this->auth = $this->Session->read('auth');
			
		// 認証クッキーチェック
		}elseif(!empty($_COOKIE['auth'])){
			
			// クッキー取得（認証者ユニークキー）
			$uniquekey = $_COOKIE['auth'];
			
			// ユーザー情報取得
			$params = array(
				'conditions' => array(
					'User.uniquekey' => $uniquekey,		// ユニークキー
					'User.is_delete' => 0,				// 削除フラグ
				),
				'recursive' => -1,
			);
			$target = $this->User->find('first',$params);
			if(!empty($target)){
				
				// セッション保存（ログイン情報）
				$this->Session->write('auth',$target['User']);
				
				// 認証者情報格納
				$this->auth = $target['User'];
				
			}
		}
		
		// 認証必須の場合
		if($this->needAuth==true){
			
			// 認証チェック
			if(empty($this->auth)){
				
				// リダイレクト（ログインへ）
				$this->redirect('/login/');
				exit;
				
			}else{
				
				// 定数宣言（再宣言除外）
				if(defined('LOGINID')==false) define('LOGINID',$this->auth['id']);
				
			}
			
		}
		
		// ユーザー一覧取得
		$params = array(
			'conditions' => array(			// 抽出条件
				'User.is_delete' => 0,
			),
			'order' => array(				// 並び順
				'User.sort' => 'ASC',
				'User.id' => 'ASC',
			),
		);
		$temps = $this->User->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $v){
				if($v['User']['is_power']!=9) $this->choices['Common']['create_id_now'][$v['User']['id']] = $v['User']['name'];									// 共通：ユーザー名 ※現職のみ
				$this->choices['Common']['create_id'][$v['User']['id']] = $v['User']['name'];																	// 共通：ユーザー名
				$this->choices['Common']['user_code'][$v['User']['id']] = $v['User']['code'];																	// 共通：ユーザーコード
				if($v['User']['is_power']!=9) $this->choices['Stock']['charge_id_now'][$v['User']['id']] = $v['User']['name'].'（'.$v['User']['code'].'）';		// 在庫：ユーザー名（ユーザーコード） ※現職のみ
				$this->choices['Stock']['charge_id'][$v['User']['id']] = $v['User']['name'].'（'.$v['User']['code'].'）';										// 在庫：ユーザー名（ユーザーコード）
			}
		}
		
		// エラーの場合
		if($this->name == 'CakeError') return;
		
	}
	
	// 顧客データ更新ファンクション
	public function MemberDataUpdate($member_id = 0){
		
		// モデル設定
		$Model = "Member";
		
		// データ更新（顧客契約履歴：使用）
		$SQL = "UPDATE member_contracts AS MC SET ";
		$SQL.= "usage_amount = (SELECT SUM(CASE WHEN MC.is_usage = 1 THEN total WHEN MC.is_usage = 2 THEN cost * quantity ELSE 0 END) FROM purchases WHERE member_id = MC.member_id AND ((contract_id IS NOT NULL AND MC.id = contract_id) || ((contract_id IS NULL OR contract_id = 0) AND MC.start_date <= date AND date <= MC.end_date)) AND is_delete = 0), ";
		$SQL.= "retail_amount = (SELECT SUM(total) FROM purchases WHERE member_id = MC.member_id AND ((contract_id IS NOT NULL AND MC.id = contract_id) || ((contract_id IS NULL OR contract_id = 0) AND MC.start_date <= date AND date <= MC.end_date)) AND is_delete = 0) ";
		$SQL.= "WHERE member_id = $member_id;";
		$this->$Model->query($SQL);
		
		// 顧客契約履歴取得
		$params = array(
			'conditions' => array(							// 抽出条件
				$Model.'Contract.member_id' => $member_id,	// 顧客ID
				$Model.'Contract.is_delete' => 0,			// 削除フラグ
			),
			'order' => array(								// 並び順
				$Model.'Contract.no' => 'ASC',
			),
			'recursive' => -1,								// 再帰階層
		);
		$temps = $this->$Model->MemberContract->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $v){
				
				// 変数移行
				$contract = $v['MemberContract'];
				
				// 前期繰越額取得
				$contract['carryover_amount'] = (!empty($carryover_amount) ? $carryover_amount : 0);
				
				// 使用目安額
				$contract['estimate_amount'] = $contract['max_lease_amount'] + $contract['carryover_amount'] + $contract['adjustment_amount'];
				
				// 残り使用額
				$contract['available_amount'] = $contract['estimate_amount'] - $contract['usage_amount'];
				
				// データ保存（顧客契約履歴）
				$this->$Model->MemberContract->save($contract);
				
				// 前期繰越額記録（残り使用可能額）
				$carryover_amount = $contract['available_amount'];
				
			}
		}
		
		// データ更新（顧客：契約内容）
		$SQL = "UPDATE members AS M SET ";
		$SQL.= "contract_sdate = (SELECT start_date FROM member_contracts WHERE member_id = M.id AND is_delete = 0 ORDER BY start_date ASC LIMIT 1), ";
		$SQL.= "contract_edate = (SELECT end_date FROM member_contracts WHERE member_id = M.id AND is_delete = 0 ORDER BY end_date DESC LIMIT 1), ";
		$SQL.= "contract_count = (SELECT COUNT(id) FROM member_contracts WHERE member_id = M.id AND is_delete = 0), ";
		$SQL.= "payment_date = (SELECT payment_date FROM member_contracts WHERE member_id = M.id AND is_delete = 0 ORDER BY end_date DESC LIMIT 1), ";
		$SQL.= "first_date = (SELECT date FROM purchases WHERE member_id = M.id AND is_delete = 0 AND date IS NOT NULL ORDER BY date ASC LIMIT 1), ";
		$SQL.= "last_date = (SELECT date FROM purchases WHERE member_id = M.id AND is_delete = 0 AND date IS NOT NULL ORDER BY date DESC LIMIT 1), ";
		$SQL.= "total_purchase_amount = (SELECT SUM(total) FROM purchases WHERE member_id = M.id AND is_delete = 0) ,";
		$SQL.= "gross_profit_amount = (SELECT SUM(profit_amount) FROM purchases WHERE member_id = M.id AND is_delete = 0) ,";
		$SQL.= "gross_profit_rate = gross_profit_amount / total_purchase_amount , ";
		$SQL.= "last_max_lease_amount = (SELECT max_lease_amount FROM member_contracts WHERE member_id = M.id AND is_delete = 0 ORDER BY end_date DESC LIMIT 1), ";
		$SQL.= "last_available_amount = (SELECT available_amount FROM member_contracts WHERE member_id = M.id AND is_delete = 0 ORDER BY end_date DESC LIMIT 1), ";
		$SQL.= "contract_services = (SELECT CONCAT('|',GROUP_CONCAT(DISTINCT is_service separator '|'),'|') FROM member_contracts WHERE member_id = M.id AND is_delete = 0) ";
		$SQL.= "WHERE id = $member_id;";
		$this->$Model->query($SQL);
		
	}
	
	// 編集ロック対象日取得
	public function EditLockDateGet(){
		
		// モデル設定
		$Model = 'Holiday';
		
		// 初期値
		$possibleDays = 5;								// 編集許可日数
		$lockDate = $baseDate = date('Y-m-d');	// 対象日･基準日
		
		// 祝日一覧取得（基準日から過去1ヶ月）
		$params = array(
			'conditions' => array(
				$Model.'.date >= ' => date('Y-m-d',strtotime($baseDate.' -1 month')),
				$Model.'.date <= ' => $baseDate,
				$Model.'.is_delete' => 0,
			),
			'fields' => array(
				$Model.'.date',$Model.'.name',
			),
			'recursive' => -1,
		);
		$holidaies = $this->$Model->find('list',$params);
		
		// 対象日算出
		do{
			
			// 対象日更新
			$lockDate = date('Y-m-d',strtotime($lockDate.' -1 day'));
			
			// 曜日取得
			$week = date('w',strtotime($lockDate));
			
			// 日数カウント（土日または祝日の場合は除外）
			if($week!=0 && $week!=6 && empty($holidaies[$lockDate])) $possibleDays--;
			
		}while($possibleDays >= 1);
		
		// 毎月月初の第6営業日目に前月以前の売上分に対してロックをかける
		$lockDate = date('Y-m-t',strtotime(substr($lockDate,0,7).'-01 -1 month'));
		
		// リターン
		return $lockDate;
		
	}
	
	// beforeRender
	public function beforeRender(){
		
		// 変数宣言
		$this->set('Model',$this->modelClass);				// モデル設定
		$this->set('auth',$this->auth);						// 認証者情報
		$this->set('choices',$this->choices);				// 選択肢
		$this->set('colors',$this->colors);					// カラー
		$this->set('tables',$this->tables);					// テーブル
		$this->set('comments',$this->comments);				// コメント（カラム）
		
		// エラーの場合
		if($this->name == 'CakeError'){
			
			// レイアウト
			$this->layout = 'error';
			
			// タイトル設定
			$this->set('title','エラー');
			
		}else{
			
			// タイトル設定
			
		}
		
	}
	
	// afterFilter
	public function afterFilter(){
		
		// $this->response->body($this->response->body());
		
	}
	
}

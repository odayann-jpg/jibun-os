<?php
App::uses('AppController', 'Controller');
App::uses('Model', 'Model');
class MembersController extends AppController{
	
	// 設定
	public $uses = array('Member','Purchase','MemberContract','Sale','Stock','InvoiceSetting');
	public $conditions = array();
	public $order = array();
	
	// beforeFilter
	public function beforeFilter(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 認証除外
		if($this->action=="customer") $this->needAuth = false;
		
		// 親へ
		parent::beforeFilter();
		
		// 短縮用変数宣言
		$this->tableName = $this->tables[$this->params['controller']];
		
		// 基本抽出条件指定
		$this->conditions = array($Model.'.is_delete' => 0);
		
		// 基本並び順指定
		$this->order = array(
			$Model.'.last_date' => 'DESC',
			$Model.'.first_date' => 'DESC',
			$Model.'.created' => 'DESC',
		);
		
		// モーダル以外
		if(strpos($this->action,"modal")===FALSE){
			
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
					$this->choices['Purchase']['stock_id'][$v['Stock']['barcode']] = $this->choices['SaleDetail']['stock_id'][$v['Stock']['barcode']] = $v['Stock']['id'];
					$this->choices['Purchase']['stock_code'][$v['Stock']['id']] = $this->choices['SaleDetail']['stock_code'][$v['Stock']['id']] = $v['Stock']['code'];
					$this->choices['Purchase']['stock_brand'][$v['Stock']['id']] = $this->choices['SaleDetail']['stock_brand'][$v['Stock']['id']] = $v['Brand']['name'];
					$this->choices['Purchase']['stock_color'][$v['Stock']['id']] = $this->choices['SaleDetail']['stock_color'][$v['Stock']['id']] = $v['Color']['name'];
					$this->choices['Purchase']['stock_size'][$v['Stock']['id']] = $this->choices['SaleDetail']['stock_size'][$v['Stock']['id']] = $v['Size']['name'];
					$this->choices['Purchase']['stock_supplier'][$v['Stock']['id']] = $this->choices['SaleDetail']['stock_supplier'][$v['Stock']['id']] = $v['Supplier']['name'];
				}
			}
			
		}
		
	}
	
	// データ移行
	public function transfer(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 既存顧客一覧取得
		$temps = $this->$Model->find('list',array('conditions'=>array($Model.'.is_delete' => 0)));
		if(!empty($temps)) $member_names = array_flip($temps);
		
		// CSVデータ取得
		$SQL = "SELECT * FROM members_CSV WHERE col2 != '' AND is_transfer = 0 ORDER BY col5 ASC , col45 ASC LIMIT 100;";
		$result = $this->$Model->query($SQL);
		foreach($result as $v){
			
			// 初期値
			$member = array();
			
			// 変数移行
			$line = $v['members_CSV'];
			
			// col1:顧客コード
			$member['transfer_mcode'] = $line['col1'];
			
			// col2:顧客名
			$member['name'] = trim(str_replace("　","",$line['col2']));
			if(!empty($member_names[$member['name']])){
				
				// 顧客ID
				$member['id'] = $member_names[$member['name']];
				
				// 移行状況（2:上書き）
				$member['is_transfer'] = 2;
				
			}else{
				
				// 移行状況（1:移行）
				$member['is_transfer'] = 1;
				
				// col4:ふりがな
				$member['kana'] = trim(str_replace("　","",mb_convert_kana($line['col4'],"KVC")));
				
				// col5:登録日
				if($line['col5']!="00/00/00") $member['created'] = date('Y-m-d 00:00:00',strtotime($line['col5']));
				
				// col6:郵便番号
				if(!empty($line['col6'])) $member['zipcode'] = $line['col6'];
				if(!empty($member['zipcode']) && $this->_prefecture_from_zip($member['zipcode'])) $member['is_prefecture'] = $this->_prefecture_from_zip($member['zipcode']);
				
				// col7:住所1 ／ col8:住所 ／ col9:住所3
				if(!empty($line['col7'])){
					$address = $this->_separate_address($line['col7']);
					if(!empty($address['state']))	$member['address'] = $address['city'].$address['other'].$line['col8'].$line['col9'];
					else							$member['address'] = $line['col7'].$line['col8'].$line['col9'];
					$member['address'] = trim(str_replace("　"," ",mb_convert_kana($member['address'],"KVa")));
				}
				
				// col10:TEL
				if(!empty($line['col10'])) $member['tel'] = $line['col10'];
				
				// col13:Email
				if(strpos($line['col13'],"@")!==FALSE) $member['email'] = $line['col13'];
				
				// col14:勤務先名
				if(!empty($line['col14'])) $member['company'] = $line['col14'];
				
				// col23:生年月日
				if($line['col23']!="00/00/00") $member['birthday'] = date('Y-m-d',strtotime($line['col23']));
				
				// col39:メモ
				if(!empty($line['col39'])) $member['memo'] = $line['col39'];
				
				// col45:更新日
				$member['modified'] = date('Y-m-d 00:00:00',strtotime($line['col45']));
				if(empty($member['created'])) $member['created'] = $member['modified'];
				
				// col53:担当者名
				switch($line['col53']){
					case 'ヨシカワ':	case '吉川':	case '吉川浩太郎':	case '吉岡':	$member['charge_id'] = 5; break;
					case '島田':		case '島野':										$member['charge_id'] = 6; break;
					case '西岡':		case '社長':										$member['charge_id'] = 3; break;
				}
				
				// ユニークキー
				if(empty($member['id'])) $member['uniquekey'] = md5(microtime(true));
				
			}
			
			// col12:携帯
			if(!empty($line['col12'])) $member['tel_mb'] = $line['col12'];
			
			// col28:性別
			if($line['col28']=="男性")		$member['is_sex'] = 1;
			elseif($line['col28']=="女性")	$member['is_sex'] = 2;
			
			// col57:来店動機
			if(!empty($line['col57'])) $member['visit_motive'] = $line['col57'];
			
			// col58:職業
			if(!empty($line['col58'])) $member['job'] = $line['col58'];
			
			// データ保存（顧客データ）
			if(empty($member['id'])) $this->$Model->create();
			if($this->$Model->save($member)){
				
				// ID取得
				if(empty($member['id'])) $member['id'] = $this->$Model->getInsertID();
				
				// データ更新（顧客CSV）
				$SQL = "UPDATE members_CSV SET is_transfer = ".$member['is_transfer']." , member_id = ".$member['id']." WHERE id = ".$line['id'].";";
				$this->$Model->query($SQL);
				
				echo '<pre>';
				var_dump($member);
				echo '</pre>';
				
			}
			
		}
		exit;
		
	}
	private function getMap() {
        return array(
            '/^0185501/' => 2, // 青森
            '/^3114411/' => 9, // 栃木
            '/^3491221/' => 9, // 栃木
            '/^3840097/' => 10, // 群馬
            '/^3890121/' => 10, // 群馬
            '/^3892261/' => 15, // 新潟
            '/^4314121/' => 23, // 愛知
            '/^4980000/' => function($address1){ return (mb_strstr($address1, '桑名郡') !== false) ? 24 : 23; }, // 三重か愛知
            '/^49808/' => 24, // 三重
            '/^520046/' => 26, // 京都
            '/^5630801/' => 28, // 兵庫
            '/^6180000/' => function($address1){ return (mb_strstr($address1, '三島郡') !== false) ? 27 : 26; }, // 大阪か京都
            '/^618000[0-4]/' => 27, //大阪
            '/^630027[1-2]/' => 27, //大阪
            '/^6471271/' => 29, //奈良
            '/^647132[1-5]/' => 24, //三重
            '/^647158/' => 29, //奈良
            '/^648030/' => 29, //奈良
            '/^6840[1-4]/' => 32, //島根
            '/^685/' => 32, //島根
            '/^8115/' => 42, //長崎
            '/^817/' => 42, //長崎
            '/^8391421/' => 44, //大分
            '/^84804/' => 42, //長崎
            '/^8710000/' => function($address1){ return (mb_strstr($address1, '築上郡') !== false) ? 40 : 44; }, // 福岡か大分
            '/^8710226/' => 40, //福岡
            '/^8710[8-9]/' => 40, //福岡
            '/^9220679/' => 18, //福井
            '/^9390171/' => 17, //石川
            '/^9498321/' => 20, //長野
            '/^(00|0[4-9])/' => 1, // 北海道
            '/^03/' => 2, // 青森県
            '/^02/' => 3, // 岩手県
            '/^98/' => 4, // 宮城県
            '/^01/' => 5, // 秋田県
            '/^99/' => 6, // 山形県
            '/^9[6-7]/' => 7, // 福島県
            '/^3[0-1]/' => 8, // 茨城県
            '/^32/' => 9, // 栃木県
            '/^37/' => 10, // 群馬県
            '/^3[3-6]/' => 11, // 埼玉県
            '/^2[6-9]/' => 12, // 千葉県
            '/^(1[0-9]|20)/' => 13, // 東京都
            '/^2[1-5]/' => 14, // 神奈川県
            '/^9[4-5]/' => 15, // 新潟県
            '/^93/' => 16, // 富山県
            '/^92/' => 17, // 石川県
            '/^91/' => 18, // 福井県
            '/^40/' => 19, // 山梨県
            '/^3[8-9]/' => 20, // 長野県
            '/^50/' => 21, // 岐阜県
            '/^4[1-3]/' => 22, // 静岡県
            '/^4[4-9]/' => 23, // 愛知県
            '/^51/' => 24, // 三重県
            '/^52/' => 25, // 滋賀県
            '/^6[0-2]/' => 26, // 京都府
            '/^5[3-9]/' => 27, // 大阪府
            '/^6[5-7]/' => 28, // 兵庫県
            '/^63/' => 29, // 奈良県
            '/^64/' => 30, // 和歌山県
            '/^68/' => 31, // 鳥取県
            '/^69/' => 32, // 島根県
            '/^7[0-1]/' => 33, // 岡山県
            '/^7[2-3]/' => 34, // 広島県
            '/^7[4-5]/' => 35, // 山口県
            '/^77/' => 36, // 徳島県
            '/^76/' => 37, // 香川県
            '/^79/' => 38, // 愛媛県
            '/^78/' => 39, // 高知県
            '/^8[0-3]/' => 40, // 福岡県
            '/^84/' => 41, // 佐賀県
            '/^85/' => 42, // 長崎県
            '/^86/' => 43, // 熊本県
            '/^87/' => 44, // 大分県
            '/^88/' => 45, // 宮崎県
            '/^89/' => 46, // 鹿児島県
            '/^90/' => 47, // 沖縄県
        );
    }
 
    /**
     * 郵便番号から県番号への変換を行ないます。
     * 
     * 厳密に県を判定する必要がない場合は、第二引数の市町村は不要です。
     * 
     * @param string $postal_code 郵便番号
     */
    function _prefecture_from_zip($postal_code, $address1 = '') {
        $postal_code = str_replace('-', '', $postal_code);
        foreach (self::getMap() as $key => $code) {
            if (preg_match($key, $postal_code)) return $code;
        }
        return 0;
    }
	function _separate_address($address){
		if (preg_match('@^(.{2,3}?[都道府県])(.+?郡.+?[町村]|.+?市.+?区|.+?[市区町村])(.+)@u', $address, $matches) !== 1) {
			return array(
				'state' => null,
				'city' => null,
				'other' => null
			);
		}
		return array(
			'state' => $matches[1],
			'city' => $matches[2],
			'other' => $matches[3],
		);
		
	}
	
	// 一覧
	public function index(){
		
		// ページ設定
		$this->set('title',$this->tableName.'一覧');
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 初期値
		$conditions = array();
		
		// 絞り込み
		if($this->Common->modeCheck('search')){
			
			// キーワード検索
			if(!empty($this->request->data[$Model]['keyword'])){
				$keywords = $this->Common->explodeEx(' ',$this->request->data[$Model]['keyword']);
				foreach($keywords as $keyword) $conditions[] = am($conditions,array('OR' => array(
					$Model.'.name LIKE "%'.$keyword.'%"',		// お名前
					$Model.'.kana LIKE "%'.$keyword.'%"',		// フリガナ
					$Model.'.company LIKE "%'.$keyword.'%"',	// 会社名
					'REPLACE('.$Model.'.tel,"-","") LIKE "%'.str_replace("-","",$keyword).'%"',		// 電話番号
					'REPLACE('.$Model.'.tel_mb,"-","") LIKE "%'.str_replace("-","",$keyword).'%"',		// 携帯番号
				)));
			}
			
			// 顧客ID
			if(!empty($this->request->data[$Model]['id'])) $conditions = am($conditions,array($Model.'.id' => $this->request->data[$Model]['id']));
			
			// 担当ユーザー
			if(isset($this->request->data[$Model]['charge_id']) && $this->request->data[$Model]['charge_id']!=="") $conditions = am($conditions,array($Model.'.charge_id' => $this->request->data[$Model]['charge_id']));
			
			// 契約開始日（日付前後チェック）
			if(!empty($this->request->data[$Model]['fsdate']) && !empty($this->request->data[$Model]['fedate']) && $this->request->data[$Model]['fsdate'] > $this->request->data[$Model]['fedate']){ $temp = $this->request->data[$Model]['fsdate']; $this->request->data[$Model]['fsdate'] = $this->request->data[$Model]['fedate']; $this->request->data[$Model]['fedate'] = $temp; }
			if(!empty($this->request->data[$Model]['fsdate'])) $conditions = am($conditions,array($Model.'.first_date >= ' => $this->request->data[$Model]['fsdate']));
			if(!empty($this->request->data[$Model]['fedate'])) $conditions = am($conditions,array($Model.'.first_date <= ' => $this->request->data[$Model]['fedate']));
			
			// 契約サービス
			if(!empty($this->request->data[$Model]['is_service'])){
				$sub_conditions = array();
				foreach($this->request->data[$Model]['is_service'] as $is_service) $sub_conditions = am($sub_conditions,array($is_service => $Model.'.contract_services LIKE "%|'.$is_service.'|%"'));
				$conditions = am($conditions,array('OR' => $sub_conditions));
			}
			
			// 誕生月
			if(isset($this->request->data[$Model]['birthmonth']) && $this->request->data[$Model]['birthmonth']!==""){
				if(!empty($this->request->data[$Model]['birthmonth']))	$conditions = am($conditions,array($Model.'.birthday LIKE "%-'.str_pad($this->request->data[$Model]['birthmonth'],2,'0',STR_PAD_LEFT).'-%"'));
				else													$conditions = am($conditions,array($Model.'.birthday' => ''));
			}
			
			// 支払い終了月
			if(isset($this->request->data[$Model]['payment_date']) && $this->request->data[$Model]['payment_date']!==""){
				if(!empty($this->request->data[$Model]['payment_date']))	$conditions = am($conditions,array($Model.'.payment_date LIKE "%-'.str_pad($this->request->data[$Model]['payment_date'],2,'0',STR_PAD_LEFT).'-%"'));
				else														$conditions = am($conditions,array($Model.'.payment_date' => ''));
			}
			
			// オプション
			if(isset($this->request->data[$Model]['option']) && $this->request->data[$Model]['option']!==""){
				foreach($this->request->data[$Model]['option'] as $option){
					switch($option){
						
						// サービス終了者を除く
						case 'finish_exclusion':
							$conditions = am($conditions,array('OR' => array(
								$Model.'.contract_edate >= ' => date('Y-m-d'),	// 契約終了日が今日以降
								$Model.'.is_continue_hold >= ' => 1						// サービス継続保留
							)));
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
		
		// ページネート設定
		$this->$Model->hasMany['MemberContract']['order'] = array('start_date' => 'DESC');
		$this->$Model->unbindModel(array('hasMany' => array('MemberFamily','MemberImpression','MemberStyling','MemberOrderdata','Purchase')));
		$this->paginate = array(
			$Model => array(
				'conditions' => am($this->conditions,$conditions),		// 抽出条件
				'order' => $this->order,								// 並び順
				'limit' => 999,											// 表示件数
				'recursive' => 1,										// 再帰階層
				'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
				'group' => $Model.'.id'
			),
		);
		
		// データ取得
		$data =  $this->paginate($Model);
		
		// 変数宣言
		$this->set(compact('data'));
		
	}
	
	// 新規登録/編集
	public function edit($id = null){
		
		// ページ設定
		$this->set('title',(!empty($id) ? $this->tableName.'編集' : '新規'.$this->tableName.'登録'));
		
		// モデル設定
		$Model = $this->modelClass;
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// エラーがない場合
			if(empty($errors)){
				
				// 文字列化
				$this->request->data[$Model]['name'] = $this->Common->implodeEx(' ',$this->request->data[$Model]['name'],false);
				$this->request->data[$Model]['kana'] = $this->Common->implodeEx(' ',$this->request->data[$Model]['kana'],false);
				$this->request->data[$Model]['birthday'] = $this->Common->implodeEx('-',$this->request->data[$Model]['birthday'],false);
				
				// データ準備
				if(empty($id)) $this->request->data[$Model]['uniquekey'] = md5(microtime(true));
				
				// データ保存
				if(empty($id)) $this->$Model->create();
				if($this->$Model->save($this->request->data)){
					
					// アラート
					if(empty($id))	$this->Common->alertSet('新しい'.$this->tableName.(!empty($this->request->data[$Model]['name']) ? '（'.$this->request->data[$Model]['name'].'）' : '').'を登録しました','success');
					else			$this->Common->alertSet($this->tableName.(!empty($this->request->data[$Model]['name']) ? '（'.$this->request->data[$Model]['name'].'）' : '').'の編集内容を保存しました','success');
					
					// ID取得
					if(empty($id)) $id = $this->$Model->getInsertID();
					
					// 欄チェック（家族構成）
					if(!empty($this->request->data[$Model.'Family'])){
						$no = 0;
						foreach($this->request->data[$Model.'Family'] as $k => $v){ if($k!="dummy"){
							
							// 編集または未削除
							if(!empty($v['id']) || empty($v['is_delete'])){
								
								// データ準備（家族構成）
								$v['member_id'] = $id;
								$v['no'] = ++$no;
								
								// データ保存（欄）
								$sModel = $Model.'Family';
								if(empty($v['id'])) $this->$Model->$sModel->create();
								$this->$Model->$sModel->save($v);
								
							}
						
						}}
					}
					
					// 欄チェック（契約履歴）
					if(!empty($this->request->data[$Model.'Contract'])){
						$no = 0;
						foreach($this->request->data[$Model.'Contract'] as $k => $v){ if($k!="dummy"){
							
							// 編集または未削除
							if(!empty($v['id']) || empty($v['is_delete'])){
								
								// データ準備（契約履歴）
								$v['member_id'] = $id;
								$v['no'] = ++$no;
								
								// データ保存（欄）
								$sModel = $Model.'Contract';
								if(empty($v['id'])) $this->$Model->$sModel->create();
								$this->$Model->$sModel->save($v);
								
							}
							
						}}
					}
					
					// 顧客データ更新
					$this->MemberDataUpdate($id);
					
					// リダイレクト（詳細画面へ）
					$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
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
					'recursive' => 1,								// 再帰階層
				);
				$this->request->data = $this->$Model->find('first',$params);
				if(empty($this->request->data)){
					
					// リダイレクト（一覧画面へ）
					$this->redirect('/' . $this->params['controller'] . '/');
					exit;
					
				}
				
				// 変数移行（家族構成）
				if(!empty($this->request->data[$Model.'Family'])){
					$temps = $this->request->data[$Model.'Family']; unset($this->request->data[$Model.'Detail']);
					foreach($temps as $temp) $this->request->data[$Model.'Family'][$temp['id']] = $temp;
				}
				
				// 変数移行（契約履歴）
				if(!empty($this->request->data[$Model.'Contract'])){
					$temps = $this->request->data[$Model.'Contract']; unset($this->request->data[$Model.'Contract']);
					foreach($temps as $temp) $this->request->data[$Model.'Contract'][$temp['id']] = $temp;
				}else{
					$this->request->data[$Model.'Contract'] = array(
						time() => array(
							'is_service' => 1,
							'start_date' => date('Y-m-d'),
							'end_date' => date('Y-m-d',strtotime('next year -1 day')),
							'is_payment' => 1,
							'consulting_fee' => 0,
							'leasing_fee' => 0,
							'max_lease_amount' => 0,
							'adjustment_amount' => 0,
						),
					);
				}
				
				// 配列化
				$this->request->data[$Model]['name'] = $this->Common->explodeEx(' ',$this->request->data[$Model]['name']);
				$this->request->data[$Model]['kana'] = $this->Common->explodeEx(' ',$this->request->data[$Model]['kana']);
				$this->request->data[$Model]['birthday'] = $this->Common->explodeEx('-',$this->request->data[$Model]['birthday']);
				
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
				
				// 初期値（契約履歴）
				$this->request->data[$Model.'Contract'] = array(
					time() => array(
						'is_service' => 1,
						'start_date' => date('Y-m-d'),
						'end_date' => date('Y-m-d',strtotime('next year -1 day')),
						'is_payment' => 1,
						'consulting_fee' => 0,
						'leasing_fee' => 0,
						'max_lease_amount' => 0,
						'adjustment_amount' => 0,
					),
				);
				
			}
			
		}
		
		// ダミー生成（家族構成）
		$this->request->data[$Model.'Family'] = array(
			'dummy' => array(
				'is_relation' => 1,
				'is_sex' => 1,
				'birthday' => '',
				'name' => '',
				'memo' => '',
			),
		) + (!empty($this->request->data[$Model.'Family']) ? $this->request->data[$Model.'Family'] : array());
		
		// ダミー生成（契約履歴）
		$this->request->data[$Model.'Contract'] = array(
			'dummy' => array(
				'is_service' => 1,
				'start_date' => date('Y-m-d'),
				'end_date' => '',
				'is_payment' => 1,
				'consulting_fee' => 0,
				'leasing_fee' => 0,
				'max_lease_amount' => 0,
				'adjustment_amount' => 0,
			),
		) + $this->request->data[$Model.'Contract'];
		
		// 変数宣言
		$this->set(compact('id'));
		
	}
	
	// 画像登録
	public function image($id = null){
		
		// ページ設定
		$this->set('title','画像登録');
		
		// モデル設定
		$Model = $this->modelClass;
		$iModel = $Model.'Impression';
		$sModel = $Model.'Styling';
		$oModel = $Model.'Orderdata';
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// ファイル仮アップロード
			foreach(array('impression_image','namecard_image') as $fld){
				if(!empty($this->request->data[$Model][$fld.'_upload']['name'])){
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$Model][$fld.'_upload'],true);
					if(empty($errorMessage)) $this->request->data[$Model][$fld] = $fileName; else $errors[$fld] = $errorMessage;
				}
				unset($this->request->data[$Model][$fld.'_upload']);
			}
			
			// ファイル仮アップロード（印象設計図画像）
			foreach($this->request->data[$iModel] as $no => $v){ if($no!="dummy" && empty($v['is_delete'])){
				
				$fld = 'file';
				if(!empty($this->request->data[$iModel][$no][$fld.'_upload']['name'])){
					
					// 拡張子取得／チェック
					$extension = substr($this->request->data[$iModel][$no][$fld.'_upload']['name'],strrpos($this->request->data[$iModel][$no][$fld.'_upload']['name'],'.'));
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$iModel][$no][$fld.'_upload'],true);
					if(empty($errorMessage)) $this->request->data[$iModel][$no][$fld] = $fileName; else $errors[$no][$fld] = $errorMessage;
					
				}
				unset($this->request->data[$iModel][$no][$fld.'_upload']);
				
			}}
			
			// ファイル仮アップロード（スタイリング画像）
			foreach($this->request->data[$sModel] as $no => $v){ if($no!="dummy" && empty($v['is_delete'])){
				
				$fld = 'file';
				if(!empty($this->request->data[$sModel][$no][$fld.'_upload']['name'])){
					
					// 拡張子取得／チェック
					$extension = substr($this->request->data[$sModel][$no][$fld.'_upload']['name'],strrpos($this->request->data[$sModel][$no][$fld.'_upload']['name'],'.'));
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$sModel][$no][$fld.'_upload'],true);
					if(empty($errorMessage)) $this->request->data[$sModel][$no][$fld] = $fileName; else $errors[$no][$fld] = $errorMessage;
					
				}
				unset($this->request->data[$sModel][$no][$fld.'_upload']);
				
			}}
			
			// ファイル仮アップロード（オーダーデータ）
			foreach($this->request->data[$oModel] as $no => $v){ if($no!="dummy" && empty($v['is_delete'])){
				
				$fld = 'file';
				if(!empty($this->request->data[$oModel][$no][$fld.'_upload']['name'])){
					
					// 拡張子取得／チェック
					$extension = substr($this->request->data[$oModel][$no][$fld.'_upload']['name'],strrpos($this->request->data[$oModel][$no][$fld.'_upload']['name'],'.'));
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$oModel][$no][$fld.'_upload'],true);
					if(empty($errorMessage)) $this->request->data[$oModel][$no][$fld] = $fileName; else $errors[$no][$fld] = $errorMessage;
					
				}
				unset($this->request->data[$oModel][$no][$fld.'_upload']);
				
			}}
			
			// エラーがない場合
			if(empty($errors)){
				
				// ファイル本アップロード（移動／削除）
				foreach(array('impression_image','namecard_image') as $fld){
					if(!empty($this->request->data[$Model][$fld])){
						if(strpos($this->request->data[$Model][$fld],"temporary_")!==FALSE)	$this->request->data[$Model][$fld] = $this->File->confirmed($this->request->data[$Model][$fld]);
						elseif($this->request->data[$Model][$fld]=="delete")						$this->request->data[$Model][$fld] = '';
					}else $this->request->data[$Model][$fld] = '';
				}
				
				// データ保存
				if(empty($id)) $this->$Model->create();
				if($this->$Model->save($this->request->data)){
					
					// 印象設計図画像チェック
					if(!empty($this->request->data[$iModel])){
						$no = 0;
						foreach($this->request->data[$iModel] as $k => $v){ if($k!="dummy"){
							
							// 編集または未削除
							if(!empty($v['id']) || empty($v['is_delete'])){
								
								// データ準備（印象設計図画像）
								$v['member_id'] = $id;
								$v['no'] = ++$no;
								
								// ファイルが仮の場合
								$fld = 'file';
								if(!empty($v[$fld]) && strpos($v[$fld],"temporary_")!==FALSE){
									
									// ファイル本アップロード（移動／削除）
									$v[$fld] = $this->File->confirmed($v[$fld]);
									
								}
								
								// データ保存（欄）
								if(empty($v['id'])) $this->$Model->$iModel->create();
								$this->$Model->$iModel->save($v);
								
							}
						
						}}
					}
					
					// スタイリング画像チェック
					if(!empty($this->request->data[$sModel])){
						$no = 0;
						foreach($this->request->data[$sModel] as $k => $v){ if($k!="dummy"){
							
							// 編集または未削除
							if(!empty($v['id']) || empty($v['is_delete'])){
								
								// データ準備（スタイリング画像）
								$v['member_id'] = $id;
								$v['no'] = ++$no;
								
								// ファイルが仮の場合
								$fld = 'file';
								if(!empty($v[$fld]) && strpos($v[$fld],"temporary_")!==FALSE){
									
									// ファイル本アップロード（移動／削除）
									$v[$fld] = $this->File->confirmed($v[$fld]);
									
								}
								
								// データ保存（欄）
								if(empty($v['id'])) $this->$Model->$sModel->create();
								$this->$Model->$sModel->save($v);
								
							}
						
						}}
					}
					
					// オーダーデータチェック
					if(!empty($this->request->data[$oModel])){
						$no = 0;
						foreach($this->request->data[$oModel] as $k => $v){ if($k!="dummy"){
							
							// 編集または未削除
							if(!empty($v['id']) || empty($v['is_delete'])){
								
								// データ準備（オーダーデータ）
								$v['member_id'] = $id;
								$v['no'] = ++$no;
								
								// ファイルが仮の場合
								$fld = 'file';
								if(!empty($v[$fld]) && strpos($v[$fld],"temporary_")!==FALSE){
									
									// ファイル本アップロード（移動／削除）
									$v[$fld] = $this->File->confirmed($v[$fld]);
									
								}
								
								// データ保存（欄）
								if(empty($v['id'])) $this->$Model->$oModel->create();
								$this->$Model->$oModel->save($v);
								
							}
						
						}}
					}
					
					// アラート
					$this->Common->alertSet($this->tableName.(!empty($this->request->data[$Model]['name']) ? '（'.$this->request->data[$Model]['name'].'）' : '').'の編集内容を保存しました','success');
					
					// リダイレクト（詳細画面へ）
					$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
					exit;
					
				}
				
			// エラーがある場合
			}else{
				
				// アラート
				$this->Common->alertSet('入力内容が正しくありません。再度確認してください','danger');
				
				// 変数宣言
				$this->set(compact('errors'));
				
			}
			
		//「一括アップロード」ボタン押下時
		}elseif($this->Common->modeCheck('lump')){
			
			// ファイル仮アップロード（欄）
			foreach($this->request->data[$Model]['lump'] as $no => $v){
				
				$fld = 'file';
				if(!empty($this->request->data[$Model]['lump'][$no]['name'])){
					
					// 拡張子取得／チェック
					$extension = substr($this->request->data[$Model]['lump'][$no]['name'],strrpos($this->request->data[$Model]['lump'][$no]['name'],'.'));
					list($fileName,$errorMessage) = $this->File->upload($this->request->data[$Model]['lump'][$no],true);
					if(empty($errorMessage)) $this->request->data[$sModel][$no][$fld] = $fileName; else $errors[$no][$fld] = $errorMessage;
					
				}
				unset($this->request->data[$Model]['lump'][$no]);
				
			}
			
			// エラーがない場合
			if(empty($errors)){
				
				// 枝番号取得
				$params = array(
					'conditions' => array(
						$sModel.'.member_id' => $id,
						$sModel.'.is_delete' => 0,
					)
				);
				$no = $this->$Model->$sModel->find('count',$params);
				
				// 欄チェック
				if(!empty($this->request->data[$sModel])){
					foreach($this->request->data[$sModel] as $k => $v){
						
						// データ準備（スタイリング画像）
						$v['member_id'] = $id;
						$v['no'] = ++$no;
						
						// ファイル本アップロード（移動／削除）
						$v['file'] = $this->File->confirmed($v['file']);
						
						// データ保存（欄）
						$this->$Model->$sModel->create();
						$this->$Model->$sModel->save($v);
					
					}
				}
				
				// アラート
				$this->Common->alertSet('スタイリング画像を一括でアップロードしました','success');
				
				// リダイレクト（画像登録画面へ）
				$this->redirect('/'.$this->params['controller'].'/image/'.$id.'/');
				exit;
				
			}
			
		}elseif(empty($this->request->data)){
			
			// 編集時
			if(!empty($id)){
				
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
					$this->redirect('/' . $this->params['controller'] . '/');
					exit;
					
				}
				
				// 変数移行（印象設計図画像）
				if(!empty($this->request->data[$iModel])){
					$temps = $this->request->data[$iModel]; unset($this->request->data[$iModel]);
					foreach($temps as $temp) $this->request->data[$iModel][$temp['id']] = $temp;
				}else{
					$this->request->data[$iModel] = array(
						time() => array(
							'name' => '',
							'file' => '',
						),
					);
				}
				
				// 変数移行（スタイリング画像）
				if(!empty($this->request->data[$sModel])){
					$temps = $this->request->data[$sModel]; unset($this->request->data[$sModel]);
					foreach($temps as $temp) $this->request->data[$sModel][$temp['id']] = $temp;
				}else{
					$this->request->data[$sModel] = array(
						time() => array(
							'name' => '',
							'file' => '',
						),
					);
				}
				
				// 変数移行（オーダーデータ）
				if(!empty($this->request->data[$oModel])){
					$temps = $this->request->data[$oModel]; unset($this->request->data[$oModel]);
					foreach($temps as $temp) $this->request->data[$oModel][$temp['id']] = $temp;
				}else{
					$this->request->data[$oModel] = array(
						time() => array(
							'name' => '',
							'file' => '',
						),
					);
				}
				
			}else{
				
				// リダイレクト（一覧画面へ）
				$this->redirect('/' . $this->params['controller'] . '/');
				exit;
				
			}
			
		}
		
		// ダミー生成（印象設計図画像）
		$this->request->data[$iModel] = array(
			'dummy' => array(
				'name' => '',
				'file' => '',
			),
		) + $this->request->data[$iModel];
		
		// ダミー生成（スタイリング画像）
		$this->request->data[$sModel] = array(
			'dummy' => array(
				'name' => '',
				'file' => '',
			),
		) + $this->request->data[$sModel];
		
		// ダミー生成（オーダーデータ）
		$this->request->data[$oModel] = array(
			'dummy' => array(
				'name' => '',
				'file' => '',
			),
		) + $this->request->data[$oModel];
		
		// 変数宣言
		$this->set(compact('id'));
		
	}
	
	// サイズ登録
	public function size($id = null){
		
		// ページ設定
		$this->set('title','サイズ登録');
		
		// モデル設定
		$Model = $this->modelClass;
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// エラーがない場合
			if(empty($errors)){
				
				// 文字列化
				$this->request->data[$Model]['pants_type'] = (!empty($this->request->data[$Model]['pants_type']) ? $this->Common->implodeEx('|',$this->request->data[$Model]['pants_type']) : '');
				$this->request->data[$Model]['socks_type'] = (!empty($this->request->data[$Model]['socks_type']) ? $this->Common->implodeEx('|',$this->request->data[$Model]['socks_type']) : '');
				$this->request->data[$Model]['size_feature'] = (!empty($this->request->data[$Model]['size_feature']) ? $this->Common->implodeEx('|',$this->request->data[$Model]['size_feature']) : '');
				
				// データ保存
				if(empty($id)) $this->$Model->create();
				if($this->$Model->save($this->request->data)){
					
					// アラート
					$this->Common->alertSet($this->tableName.(!empty($this->request->data[$Model]['name']) ? '（'.$this->request->data[$Model]['name'].'）' : '').'の編集内容を保存しました','success');
					
					// リダイレクト（詳細画面へ）
					$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
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
			
			// 編集時
			if(!empty($id)){
				
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
					$this->redirect('/' . $this->params['controller'] . '/');
					exit;
					
				}
				
				// 配列化
				$this->request->data[$Model]['pants_type'] = $this->Common->explodeEx('|',$this->request->data[$Model]['pants_type']);
				$this->request->data[$Model]['socks_type'] = $this->Common->explodeEx('|',$this->request->data[$Model]['socks_type']);
				$this->request->data[$Model]['size_feature'] = $this->Common->explodeEx('|',$this->request->data[$Model]['size_feature']);
				
			}else{
				
				// リダイレクト（一覧画面へ）
				$this->redirect('/' . $this->params['controller'] . '/');
				exit;
				
			}
			
		}
		
	}
	
	// 詳細
	public function view($id = null){
		
		// ページ設定
		$this->set('title',$this->tableName.'詳細');
		
		// モデル設定
		$Model = $this->modelClass;
		
		// セッションチェック
		if($this->Session->check($this->viewPath.'indexData')){
			
			// セッション破棄
			$this->Session->delete($this->viewPath.'indexData');
			$this->Session->delete($this->viewPath.'indexConditions');
			
		}
		
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
			
		//「＋」ボタン押下時
		}elseif($this->Common->modeCheck('send')){
			
			// ファイル仮アップロード
			$fld = 'attachment';
			if(!empty($this->request->data[$Model][$fld.'_upload']['name'])){
				list($fileName,$errorMessage) = $this->File->upload($this->request->data[$Model][$fld.'_upload']);
				if(empty($errorMessage)) $$fld = $fileName; else $errors[$fld] = $errorMessage;
			}
			
			// データ保存（対応履歴）
			$this->$Model->MemberReception->create();
			$this->$Model->MemberReception->save(array(
				'member_id' => $detail[$Model]['id'],						// 保証契約
				'date' => date('Y-m-d H:i:s'),						// 日時
				'content' => $this->request->data[$Model]['content'],		// 内容
				'attachment' => (!empty($attachment) ? $attachment : ''),	// 添付ファイル
			));
			
			// アラート
			$this->Common->alertSet('対応履歴を追加しました','success');
			
			// リダイレクト（詳細画面へ）
			$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
			exit;
			
		//「×」ボタン押下時
		}elseif($this->Common->modeCheck('rdelete')){
			
			// コードチェック
			if(!empty($_GET['id']) && $_GET['code']==md5($this->params['controller'].$_GET['id'])){
				
				// データ更新（削除フラグ）
				$this->$Model->MemberReception->id = $_GET['id'];
				$this->$Model->MemberReception->saveField('is_delete',true);
				
				// アラート
				$this->Common->alertSet('対応履歴を削除しました','warning');
				
			}
			
			// リダイレクト（詳細画面へ）
			$this->redirect('/'.$this->params['controller'].'/view/'.$id.'/');
			exit;
			
		//「削除する」ボタン押下時
		}elseif($this->Common->modeCheck('delete')){
			
			// コードチェック
			if($_GET['code']==md5($this->params['controller'].$id)){
				
				// データ更新（削除フラグ）
				$this->$Model->id = $id;
				$this->$Model->saveField('is_delete',true);
				
				// アラート
				$this->Common->alertSet($this->tableName.(!empty($detail[$Model]['name']) ? '（'.$detail[$Model]['name'].'）' : '').'を削除しました','warning');
				
			}
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 配列化
		$detail[$Model]['pants_type'] = $this->Common->explodeEx('|',$detail[$Model]['pants_type']);
		$detail[$Model]['socks_type'] = $this->Common->explodeEx('|',$detail[$Model]['socks_type']);
		$detail[$Model]['size_feature'] = $this->Common->explodeEx('|',$detail[$Model]['size_feature']);
		
		// 年齢取得
		$detail[$Model]['age'] = $this->Common->ageGet($detail[$Model]['birthday']);
		
		// 家族構成がある場合
		if(!empty($detail['MemberFamily'])){
			foreach($detail['MemberFamily'] as $k => $v){
				$detail['MemberFamily'][$k]['age'] = (!empty($v['birthday']) ? $this->Common->ageGet($v['birthday']) : 0);
			}
		}
		
		// 初期値
		$cost_amounts = array();
		
		// リースアイテム契約履歴別分割
		if(!empty($detail['Purchase'])){
			
			// 変数移行／初期化
			$purchases = $detail['Purchase'];
			unset($detail['Purchase']);
			
			// リースアイテム分ループ
			foreach($purchases as $purchase){
				
				// 在庫商品の場合
				if($purchase['is_stock']==1 && !empty($purchase['barcode'])){
					
					// 在庫ID取得
					$stock_id = $this->choices['Purchase']['stock_id'][$purchase['barcode']];
					
					// 各名称反映
					$purchase['stock_id'] = $stock_id;
					$purchase['brand_name'] = $this->choices['Purchase']['stock_brand'][$stock_id];
					$purchase['color'] = $this->choices['Purchase']['stock_color'][$stock_id];
					$purchase['size'] = $this->choices['Purchase']['stock_size'][$stock_id];
					$purchase['bs'] = $this->choices['Purchase']['stock_supplier'][$stock_id];
					
				}
				
				// 契約履歴チェック／ループ
				if(!empty($detail['MemberContract'])){
					foreach($detail['MemberContract'] as $contract){
						
						// 対象契約 または 契約期間内の場合
						if(
							(!empty($purchase['contract_id']) && $purchase['contract_id']==$contract['id']) || 
							(empty($purchase['contract_id']) && $contract['start_date'] <= $purchase['date'] && $purchase['date'] <= $contract['end_date'])
						){
							
							// 変数格納
							$purchase['no'] = (!empty($detail['Purchase'][$contract['no']]) ? count($detail['Purchase'][$contract['no']]) + 1 : 1);
							$detail['Purchase'][$contract['no']][$purchase['id']] = $purchase;
							
							// 下代合計加算
							if(!empty($cost_amounts[$contract['no']])) $cost_amounts[$contract['no']] += $purchase['cost']; else $cost_amounts[$contract['no']] = $purchase['cost'];
							
						}
						
					}
				}
				
				// 購入日時が空かつ対象契約指定なしの場合
				if($purchase['date']==NULL && empty($purchase['contract_id'])){
					
					// 変数格納
					$purchase['no'] = (!empty($detail['Purchase']['blank']) ? count($detail['Purchase']['blank']) + 1 : 1);
					$detail['Purchase']['blank'][$purchase['id']] = $purchase;
					
				}
				
			}
			
		}
		
		// 並び替え（契約履歴：新しい順）
		krsort($detail['MemberContract']);
		if(!empty($detail['MemberContract'])){
			foreach($detail['MemberContract'] as $k => $contract){
				
				// 変数宣言
				$detail['MemberContract'][$k]['cost_amount'] = (!empty($cost_amounts[$contract['no']]) ? $cost_amounts[$contract['no']] : 0);
				
				// 並び替え（契約履歴：新しい順）
//				if(!empty($detail['Purchase'][$contract['no']])) krsort($detail['Purchase'][$contract['no']]);
				
			}
		}
		
		// 契約名称リスト化
		if(!empty($detail['MemberContract'])){
			foreach($detail['MemberContract'] as $contract) $this->choices['Purchase']['contract_id'][$contract['id']] = $contract['name'];
		}
		
		// 購入履歴（売上詳細）取得
		$params = array(
			'conditions' => array(								// 抽出条件
				'Sale.member_id' => $detail[$Model]['id'],		// 顧客ID（該当顧客）
				'Sale.is_delete' => 0,							// 削除フラグ（0:通常）
				'SaleDetail.is_delete' => 0,					// 削除フラグ（0:通常）
			),
			'order' => array(									// 並び順
				'Sale.date' => 'DESC',
				'SaleDetail.no' => 'ASC',
			),
		);
		$temps = $this->Sale->SaleDetail->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $temp){
				
				// 変数移行
				$v = $temp['SaleDetail'];
				$v['Sale'] = $temp['Sale'];
				
				// 商品コードがある場合
				if(!empty($v['code']) && !empty($this->choices['SaleDetail']['stock_id'][$v['barcode']])){
					
					// 在庫ID取得
					$stock_id = $this->choices['SaleDetail']['stock_id'][$v['barcode']];
					
					// 各名称反映
					$v['stock_id'] = $stock_id;
					$v['brand_name'] = $this->choices['SaleDetail']['stock_brand'][$stock_id];
					$v['color'] = $this->choices['SaleDetail']['stock_color'][$stock_id];
					$v['size'] = $this->choices['SaleDetail']['stock_size'][$stock_id];
					$v['bs'] = $this->choices['SaleDetail']['stock_supplier'][$stock_id];
					
				}
				
				// 変数格納
				$detail['SaleDetail'][] = $v;
				
			}
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
	
	// 調整額登録
	public function adjustment_amount($member_id = null , $contract_id = null , $amount = null){
		
		// モデル設定
		$Model = $this->modelClass;
		$cModel = $Model.'Contract';
		
		// 初期値
		$response = array('result' => 'NG');
		
		// データチェック
		if(!empty($member_id) && !empty($contract_id)){
			
			// データ取得
			$params = array(
				'conditions' => array(							// 抽出条件
					$cModel.'.id' => $contract_id,				// ID
					$cModel.'.member_id' => $member_id,			// 顧客ID
					$cModel.'.is_delete' => 0,					// 削除フラグ（0:通常）
				),
				'recursive' => -1,								// 再帰階層
			);
			$contract = $this->$Model->$cModel->find('first',$params);
			if(!empty($contract)){
				
				// 数値化
				$amount = (int)$amount;
				
				// データ更新（調整額）
				$this->$Model->$cModel->id = $contract_id;
				$this->$Model->$cModel->saveField('adjustment_amount',$amount);
				
				// 顧客データ更新
				$this->MemberDataUpdate($member_id);
				
				// 処理結果
				$response['result'] = 'OK';
				
			}
			
		}
		
		// リターン
		echo json_encode($response);
		Configure::write('debug',0); exit;
		
	}
	
	// 一覧
	public function reception($id = null){
		
		// レイアウト
		$this->layout = null;
		
		// モデル設定
		$Model = $this->modelClass;
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// データ保存
			if($this->$Model->MemberReception->save($this->request->data)){
				
				// アラート
				$this->Common->alertSet('対応履歴を変更しました','success');
				
				// リダイレクト（詳細画面へ）
				$this->redirect('/'.$this->params['controller'].'/view/'.$this->request->data['MemberReception']['member_id'].'/');
				exit;
				
			}
			
		}elseif(empty($this->request->data)){
			
			// データ取得
			$params = array(
				'conditions' => am(array(						// 抽出条件
					'MemberReception.id' => $id,				// ID
					'MemberReception.is_delete' => 0,			// 削除フラグ（0:通常）
				)),
				'recursive' => -1,								// 再帰階層
			);
			$this->request->data = $this->$Model->MemberReception->find('first',$params);
			if(empty($this->request->data)) exit;
			
		}
		
	}
	
	// お客様用（検索）
	public function customer_search(){
		
		// レイアウト設定
		$this->layout = 'login';
		
		// ページ設定
		$this->set('title','お客様検索');
		
		// モデル設定
		$Model = $this->modelClass;
		
		//「保存する」ボタン押下時
		if($this->Common->modeCheck($this->action)){
			
			// データ取得
			$params = array(
				'conditions' => am($this->conditions,array(		// 抽出条件
					$Model.'.id' => $this->request->data[$Model]['id'],			// ID
				)),
				'recursive' => -1,								// 再帰階層
			);
			$detail = $this->$Model->find('first',$params);
			if(!empty($detail)){
				
				// リダイレクト（お客様専用画面へ）
				$this->redirect('/customer/'.$detail[$Model]['uniquekey'].'/');
				exit;
				
			}
			
		}
		
		// 顧客一覧取得
		$params = array(
			'conditions' => $this->conditions,
			'order' => array(
				$Model.'.kana' => 'ASC',	
			),
			'recursive' => -1,
		);
		$temps = $this->$Model->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $k => $v) $this->choices[$Model]['id'][$v[$Model]['id']] = $v[$Model]['name'].'（'.$v[$Model]['kana'].'）';
		}
		
	}
	
	// お客様用（詳細）
	public function customer(){
		
		// モデル設定
		$Model = $this->modelClass;
		
		// 変数移行
		$uniquekey = $this->params['uniquekey'];
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.uniquekey' => $uniquekey,			// ユニークキー
			)),
			'recursive' => 1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)) exit;
		
		// ページ設定
		$this->layout = 'customer';
		$this->set('title',$detail[$Model]['name'].'様');
		
		// 配列化
		$detail[$Model]['pants_type'] = $this->Common->explodeEx('|',$detail[$Model]['pants_type']);
		$detail[$Model]['socks_type'] = $this->Common->explodeEx('|',$detail[$Model]['socks_type']);
		$detail[$Model]['size_feature'] = $this->Common->explodeEx('|',$detail[$Model]['size_feature']);
		
		// 年齢取得
		$detail[$Model]['age'] = $this->Common->ageGet($detail[$Model]['birthday']);
		
		// 初期値
		$cost_amounts = array();
		
		// リースアイテム契約履歴別分割
		if(!empty($detail['Purchase'])){
			
			// 変数移行／初期化
			$purchases = $detail['Purchase'];
			unset($detail['Purchase']);
			
			// リースアイテム分ループ
			foreach($purchases as $purchase){
				
				// 在庫商品の場合
				if($purchase['is_stock']==1 && !empty($purchase['barcode'])){
					
					// 在庫ID取得
					$stock_id = $this->choices['Purchase']['stock_id'][$purchase['barcode']];
					
					// 各名称反映
					$purchase['stock_id'] = $stock_id;
					$purchase['brand_name'] = $this->choices['Purchase']['stock_brand'][$stock_id];
					$purchase['color'] = $this->choices['Purchase']['stock_color'][$stock_id];
					$purchase['size'] = $this->choices['Purchase']['stock_size'][$stock_id];
					$purchase['bs'] = $this->choices['Purchase']['stock_supplier'][$stock_id];
					
				}
				
				// 契約履歴チェック／ループ
				if(!empty($detail['MemberContract'])){
					foreach($detail['MemberContract'] as $contract){
						
						// 対象契約 または 契約期間内の場合
						if(
							(!empty($purchase['contract_id']) && $purchase['contract_id']==$contract['id']) || 
							(empty($purchase['contract_id']) && $contract['start_date'] <= $purchase['date'] && $purchase['date'] <= $contract['end_date'])
						){
							
							// 変数格納
							$purchase['no'] = (!empty($detail['Purchase'][$contract['no']]) ? count($detail['Purchase'][$contract['no']]) + 1 : 1);
							$detail['Purchase'][$contract['no']][$purchase['id']] = $purchase;
							
							// 下代合計加算
							if(!empty($cost_amounts[$contract['no']])) $cost_amounts[$contract['no']] += $purchase['cost']; else $cost_amounts[$contract['no']] = $purchase['cost'];
							
						}
						
					}
				}
				
				// 購入日時が空かつ対象契約指定なしの場合
				if($purchase['date']==NULL && $purchase['contract_id']!=$contract['id']){
					
					// 変数格納
					$purchase['no'] = (!empty($detail['Purchase']['blank']) ? count($detail['Purchase']['blank']) + 1 : 1);
					$detail['Purchase']['blank'][$purchase['id']] = $purchase;
					
				}
				
			}
			
		}
		
		// 並び替え（契約履歴：新しい順）
		krsort($detail['MemberContract']);
		if(!empty($detail['MemberContract'])){
			foreach($detail['MemberContract'] as $k => $contract){
				
				// 変数宣言
				$detail['MemberContract'][$k]['cost_amount'] = (!empty($cost_amounts[$contract['no']]) ? $cost_amounts[$contract['no']] : 0);
				
				// 並び替え（契約履歴：新しい順）
//				if(!empty($detail['Purchase'][$contract['no']])) krsort($detail['Purchase'][$contract['no']]);
				
			}
		}
		
		// 契約名称リスト化
		if(!empty($detail['MemberContract'])){
			foreach($detail['MemberContract'] as $contract) $this->choices['Purchase']['contract_id'][$contract['id']] = $contract['name'];
		}
		
		// 変数宣言
		$this->set(compact('detail'));
		
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
		
		// データ取得
		$this->$Model->hasMany['MemberContract']['order'] = array('start_date' => 'DESC');
		$params = array(
			'conditions' => am($this->conditions,$conditions),		// 抽出条件
			'order' => $this->order,								// 並び順
			'recursive' => 1,										// 再帰階層
			'page' => (!empty($_GET['page']) ? $_GET['page'] : 1),	// ページ
		);
		$data =  $this->$Model->find('all',$params);
		if(!empty($data)){
			
			// 出力項目「基本セット」の場合
			if(isset($this->request->data[$Model]['is_output']) && $this->request->data[$Model]['is_output']==0){
				
				// 項目名
				$fields = array(
					'顧客ID',
					'担当者',
					'お名前',
					'フリガナ',
					'会社名',
					'電話番号',
					'携帯番号',
					'郵便番号',
					'住所',
					'メールアドレス',
					'請求先',
					'営業ｺﾝｻﾙ費/顧問料',
					'現契約サービス',
					'支払い終了月',
					'生年月日',
					'最終購入日',
					'リース上限額',
					'残り使用金額',
					'最新対応履歴',
					'備考',
				);
				
			// 出力項目「お名前＋メールアドレス」の場合
			}elseif($this->request->data[$Model]['is_output']==1){
				
				// 項目名
				$fields = array(
					'お名前',
					'メールアドレス',
				);
				
			}
			
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
				
				// 出力項目「基本セット」の場合
				if(isset($this->request->data[$Model]['is_output']) && $this->request->data[$Model]['is_output']==0){
					
					// 顧客ID
					$fld = 'id';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 担当者
					$fld = 'charge_id';
					$csv .= '"'.(!empty($this->choices['Common']['create_id'][$v[$Model][$fld]]) ? $this->choices['Common']['create_id'][$v[$Model][$fld]] : '').'",';
					
					// お名前
					$fld = 'name';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// フリガナ
					$fld = 'kana';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 会社名
					$fld = 'company';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 電話番号
					$fld = 'tel';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 携帯番号
					$fld = 'tel_mb';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 郵便番号
					$fld = 'zipcode';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 住所（都道府県＋送付先住所）
					$fld = 'is_prefecture'; $fld2 = 'address';
					$csv .= '"'.(!empty($v[$Model][$fld]) ? $this->choices[$Model][$fld][$v[$Model][$fld]] : '').$v[$Model][$fld2].'",';
					
					// メールアドレス
					$fld = 'email';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 請求先
					$fld = 'is_billing';
					$csv .= '"'.$this->choices[$Model][$fld][$v[$Model][$fld]].'",';
					
					// 営業ｺﾝｻﾙ費/顧問料
					$fld = 'consulting_fee';
					$csv .= '"'.(!empty($v['MemberContract']) ? $v['MemberContract'][0][$fld] : '').'",';
					
					// 現契約サービス
					$fld = 'is_service';
					$csv .= '"'.(!empty($v['MemberContract']) ? $this->choices['MemberContract'][$fld][$v['MemberContract'][0][$fld]] : '').'",';
					
					// 支払い終了月
					$fld = 'payment_date';
					$csv .= '"'.($v[$Model][$fld]!="0000-00-00" ? $v[$Model][$fld] : '').'",';
					
					// 生年月日
					$fld = 'birthday';
					$csv .= '"'.(!empty($v[$Model][$fld]) && $v[$Model][$fld]!="0000-00-00" ? $v[$Model][$fld] : '').'",';
					
					// 最終購入日
					$fld = 'last_date';
					$csv .= '"'.(!empty($v[$Model][$fld]) && $v[$Model][$fld]!="0000-00-00" ? $v[$Model][$fld] : '').'",';
					
					// リース上限額
					$fld = 'last_max_lease_amount';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 残り使用金額
					$fld = 'last_available_amount';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// 最新対応履歴
					if(!empty($v['MemberReception'])){
						$reception = end($v['MemberReception']);
						$csv .= '"'.str_replace("\r\n"," ",$reception['content']).'",';
					}else $csv .= '"",';
					
					// 備考
					$fld = 'memo';
					$csv .= '"'.(!empty($v[$Model][$fld]) ? str_replace("\r\n"," ",$v[$Model][$fld]) : '').'",';
					
				// 出力項目「お名前＋メールアドレス」の場合
				}elseif($this->request->data[$Model]['is_output']==1){
					
					// お名前
					$fld = 'name';
					$csv .= '"'.$v[$Model][$fld].'",';
					
					// メールアドレス
					$fld = 'email';
					$csv .= '"'.$v[$Model][$fld].'",';
					
				}
				
				// 最終カンマ除去
				$csv = rtrim($csv,",");
				
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
	
	// 15%ありへ
	public function per15_on($member_id = null){
    	
		// モデル設定
		$Model = $this->modelClass;
		$pModel = 'Purchase';
		
		// データチェック
		if(empty($member_id) || empty($this->request->data['target'])){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $member_id,					// ID
			)),
			'recursive' => -1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 変数移行
		$member = $detail[$Model];
		$targets = implode(",",$this->request->data['target']);
		
		// 再計算（15%あり）
		$SQL = "UPDATE purchases SET is_selection = 1 , total = price * quantity * 1.15 , ";
		$SQL.= "profit_amount = total - (cost * quantity) , profit_rate = profit_amount / total WHERE member_id = ".$member['id']." AND id IN (".($targets).");";
		$this->$Model->query($SQL);
		
		// 顧客データ更新
		$this->MemberDataUpdate($member['id']);
		
		// アラート
		$this->Common->alertSet('対象のリースアイテムを「15%あり」に変更しました','success');
		
		// リダイレクト（詳細画面へ）
		$this->redirect('/'.$this->params['controller'].'/view/'.$member['id'].'/');
		exit;
		
	}
	
	// 15%なしへ
	public function per15_off($member_id = null){
    	
		// モデル設定
		$Model = $this->modelClass;
		$pModel = 'Purchase';
		
		// データチェック
		if(empty($member_id) || empty($this->request->data['target'])){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $member_id,					// ID
			)),
			'recursive' => -1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 変数移行
		$member = $detail[$Model];
		$targets = implode(",",$this->request->data['target']);
		
		// 再計算（15%なし）
		$SQL = "UPDATE purchases SET is_selection = 0 , total = price * quantity * 1 , ";
		$SQL.= "profit_amount = total - (cost * quantity) , profit_rate = profit_amount / total WHERE member_id = ".$member['id']." AND id IN (".($targets).");";
		$this->$Model->query($SQL);
		
		// 顧客データ更新
		$this->MemberDataUpdate($member['id']);
		
		// アラート
		$this->Common->alertSet('対象のリースアイテムを「15%なし」に変更しました','success');
		
		// リダイレクト（詳細画面へ）
		$this->redirect('/'.$this->params['controller'].'/view/'.$member['id'].'/');
		exit;
		
	}
	
	// 残り使用可能額（モーダル）
	public function modal_available(){
    	
    	// レイアウト
		$this->layout = false;
		
	}
	
	// 領収書出力
	public function receipt($member_id = null){
    	
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// データ取得
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// モデル設定
		$Model = $this->modelClass;
		$cModel = $Model.'Contract';
		$pModel = 'Purchase';
		
		// データチェック
		if(empty($member_id) || empty($this->request->data['target'])){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// データ取得
		$params = array(
			'conditions' => am($this->conditions,array(		// 抽出条件
				$Model.'.id' => $member_id,					// ID
			)),
			'recursive' => -1,								// 再帰階層
		);
		$detail = $this->$Model->find('first',$params);
		if(empty($detail)){
			
			// リダイレクト（一覧画面へ）
			$this->redirect('/'.$this->params['controller'].'/');
			exit;
			
		}
		
		// 変数移行
		$member = $detail[$Model];
		$targets = $this->request->data['target'];
		
		// リースアイテム一覧取得
		$params = array(
			'conditions' => array($pModel.'.id' => $targets),	// 抽出条件
			'order' => array($pModel.'.id' => 'DESC'),			// 並び順
			'recursive' => -1,									// 再帰階層
		);
		$temps = $this->$pModel->find('all',$params);
		if(!empty($temps)){
			foreach($temps as $k => $v){
				
				// 合計金額合算
				$contract['bill_amount'] += $v[$pModel]['total'];
				
				// 変数移行
				$list[$v[$pModel]['id']] = $v[$pModel];
				
			}
		}
		$detailCount = (!empty($list) ? count($list) : 0);
		
		// 請求書設定取得
		$temp = $this->InvoiceSetting->read(null,1);
		if(!empty($temp)){
			foreach($temp['InvoiceSetting'] as $column => $value){
				if($column!="id") $contract[$column] = $value;
			}
		}
		
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
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->SetXY(152,38.25);
		$tcpdf->Cell(30,4,date('Y/n/j'),0,0,'C');
		
		// 合計金額
		$tcpdf->SetFont("kozgopromedium", null, 13);
		$tcpdf->SetXY(64,63.5);
		$tcpdf->Cell(30,9,"¥".number_format($contract['bill_amount']),0,0,'R');
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		// 郵便番号
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(115, 49, "〒".$contract['inhouse_zipcode']);
		
		// 住所
		$tcpdf->SetFont("kozminproregular", null, 9);
		$tcpdf->Text(115, 54, $contract['inhouse_address']);
		
		// 会社名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(115, 59, $contract['inhouse_company']);
		
		// 代表者名
		$tcpdf->SetFont("kozgopromedium", "B", 11);
		$tcpdf->Text(115, 65, $contract['inhouse_name']);
		
		// 電話番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(115,71, "TEL  ".$contract['inhouse_tel']);
		
		// 登録番号
		$tcpdf->SetFont("kozminproregular", null, 8);
		$tcpdf->Text(115, 80, "登録番号：".$contract['inhouse_licenseno']);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// 自社情報（社印）
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		if(!empty($contract['inhouse_comseal'])){
			
			// ファイル取得
			$fileName = $contract['inhouse_comseal'];
			
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
				if($templateCode==12)		$y = 93.75 + ($count*6.425);
				elseif($templateCode==25)	$y = 90 + ($count*4.025);
				elseif($templateCode==45)	$y = 88.7 + ($count*2.95);
				
				// 品名
				$tcpdf->SetFont("kozminproregular", null, $fontSize);
				$tcpdf->SetDrawColor(255,0,0);
				$tcpdf->SetXY(32,$y - 1.2);
				$line['item_name'] = str_replace("　","",$line['item_name']);
				$line['color'] = str_replace("　","",$line['color']);
				$line['size'] = str_replace("　","",$line['size']);
				$name = 
					(!empty($line['item_name']) ? $line['item_name'] : ' ')					// ＋品名
					.(!empty($line['color']) ? '/'.$line['color'] : '')						// ＋カラー
					.(!empty($line['size']) ? '/'.$line['size'] : '');						// ＋サイズ
				$tcpdf->Cell(65,6.1,$name,0,0,'L','','',1);
				
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
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(124,191.75);
			$tcpdf->Cell(25,6.5,number_format($contract['bill_amount']),0,0,'R');
			
		}elseif($templateCode==25){
			
			// フォントサイズ指定
			$fontSize = 8;
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(124,202.25);
			$tcpdf->Cell(25,6.5,number_format($contract['bill_amount']),0,0,'R');
			
		}elseif($templateCode==45){
			
			// フォントサイズ指定
			$fontSize = 6.5;
			
			// 合計(税込)
			$tcpdf->SetFont("kozgopromedium", "B", $fontSize);
			$tcpdf->SetXY(124,228.65);
			$tcpdf->Cell(25,6.5,number_format($contract['bill_amount']),0,0,'R');
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		// PDF出力
		/////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		//$pdf->Output(出力時のファイル名, 出力モード);
		$tcpdf->Output("output.pdf", "I");	// インライン表示
	//	$tcpdf->Output("output.pdf", "D");	// ダウンロード
		
	}
		
	// 共通アクション呼び出し
	public function common($action = null){
		
		// 初期値
		$options = array();
		
		// アクション呼び出し
		if(!empty($action)) $this->$action($options);
		
	}
	
}

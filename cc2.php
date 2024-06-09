<?php
//ini_set("display_errors", On);
//error_reporting(E_ALL);
error_reporting(E_ERROR | E_WARNING);

 if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/***************************************************************************************************
 **************************************************************************************************/

class CC2 {

    function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->library('curl');
		$this->CI->load->model('model_apply');
		$this->CI->load->library('session');
       	log_message('debug', 'CC2 Class Initialized');
    }


    function gmo_order($post, $insert_id, $data, $paymethod) {
//		$shopId = 'tshop00027430';
//		$shopPass = '5wabpahf';
		$shopId = '9101169839653';
		$shopPass = 'znzhar7b';
		$tax = 0;
		$tdFlag = 0;
		$itemCode = null;
		$jobCd = 'CAPTURE';
		$post = $post;
		$myinput = $this->CI->input;
		$myitem = $this->CI->model_apply->get_item_by_id($myinput->post('item_id'));
		require_once '/home/sample-office/www/op/src/config.php';

		if(!empty( $insert_id )) {
			require_once '/home/sample-office/www/op/src/com/gmo_pg/client/input/EntryTranInput.php';
			require_once '/home/sample-office/www/op/src/com/gmo_pg/client/input/ExecTranInput.php';
			require_once '/home/sample-office/www/op/src/com/gmo_pg/client/input/EntryExecTranInput.php';
			require_once '/home/sample-office/www/op/src/com/gmo_pg/client/tran/EntryExecTran.php';

			//入力パラメータクラスをインスタンス化します
			//取引登録時に必要なパラメータ
			$entryInput = new EntryTranInput();
			$entryInput->setShopId( $shopId );
			$entryInput->setShopPass( $shopPass );
			$entryInput->setJobCd( $jobCd );
			$entryInput->setOrderId( $insert_id );
			$entryInput->setItemCode( $itemCode );
			$entryInput->setAmount( $myitem->amount );
			$entryInput->setTax( $ttax );
			$entryInput->setTdFlag( $tdFlag );
			$entryInput->setTdTenantName( '' /* $_POST['TdTenantName'] */ );

			//決済実行のパラメータ
			$execInput = new ExecTranInput();

			//カード番号入力型・会員ID決済型に共通する値です。
			$execInput->setOrderId( $insert_id );
			
			//支払方法に応じて、支払回数のセット要否が異なります。
			$method = $paymethod;
			$execInput->setMethod( $method );

			if( $method == '2' || $method == '4'){//支払方法が、分割またはボーナス分割の場合、支払回数を設定します。
				$execInput->setPayTimes( $post['paynum'] );
			}
			//このサンプルでは、加盟店自由項目１～３を全て利用していますが、これらの項目は任意項目です。
			//利用しない場合、設定する必要はありません。
			//また、加盟店自由項目に２バイトコードを設定する場合、SJISに変換して設定してください。
			$execInput->setClientField1( mb_convert_encoding( $_POST['ClientField1'] , 'SJIS' , PGCARD_SAMPLE_ENCODING ) );
			$execInput->setClientField2( mb_convert_encoding( $_POST['ClientField2'] , 'SJIS' , PGCARD_SAMPLE_ENCODING ) );
			$execInput->setClientField3( mb_convert_encoding( $_POST['ClientField3'] , 'SJIS' , PGCARD_SAMPLE_ENCODING ) );
			$execInput->setDisplayInfo( mb_convert_encoding( $_POST['DisplayInfo'] , 'SJIS' , PGCARD_SAMPLE_ENCODING ) );
			
			//HTTP_ACCEPT,HTTP_USER_AGENTは、3Dセキュアサービスをご利用の場合のみ必要な項目です。
			//Entryで3D利用フラグをオンに設定した場合のみ、設定してください。	
			//設定する場合、カード所有者のブラウザから送信されたリクエストヘッダの値を、無加工で
			//設定してください。
			$execInput->setHttpUserAgent( $_SERVER['HTTP_USER_AGENT']);
			$execInput->setHttpAccept( $_SERVER['HTTP_ACCEPT' ]);
			
			//ここから、カード番号入力型決済と会員ID型決済それぞれの場合で
			//異なるパラメータを設定します。
			
			//ここでは、「画面で会員IDが入力されたか」を判断基準にして、
			//決済のタイプを判別しています。
			$memberId = $_POST['MemberID'];

			if( 0 < strlen( $memberId )  ){//会員ID決済
				//サンプルでは、サイトID・サイトパスワードはコンスタント定義しています。
				$execInput->setSiteId( PGCARD_SITE_ID );
				$execInput->setSitePass( PGCARD_SITE_PASS );
				
				//会員IDは必須です。
				$execInput->setMemberId( $memberId );
				
				//登録カード連番は任意です。
				$cardSeq = $_POST['CardSeq'];
				if( 0< strlen( $cardSeq ) ){
					$execInput->setCardSeq( $cardSeq );
				}
				
			} else {//カード番号決済
				
				//カード番号・有効期限は必須です。
				$execInput->setCardNo( $post['cn'] );
				$execInput->setExpire( $post['cardYear']. $post['cardMonth'] );
				
				//セキュリティコードは任意です。
				$execInput->setSecurityCode( $post['cvv2'] );
			}

			//取引登録＋決済実行の入力パラメータクラスをインスタンス化します
			$input = new EntryExecTranInput();/* @var $input EntryExecTranInput */
			$input->setEntryTranInput( $entryInput );
			$input->setExecTranInput( $execInput );
			
			//API通信クラスをインスタンス化します
			$exe = new EntryExecTran();/* @var $exec EntryExecTran */

			//パラメータオブジェクトを引数に、実行メソッドを呼びます。
			//正常に終了した場合、結果オブジェクトが返るはずです。
			$output = $exe->exec( $input );/* @var $output EntryExecTranOutput */

			//実行後、その結果を確認します。

			if( $exe->isExceptionOccured() ){//取引の処理そのものがうまくいかない（通信エラー等）場合、例外が発生します。

				return $output;
				//サンプルでは、例外メッセージを表示して終了します。
				require_once('/home/sample-office/www/op/src/sample/display/Exception.php');
				exit;
				
			} else {
				
				//例外が発生していない場合、出力パラメータオブジェクトが戻ります。
				if( $output->isErrorOccurred() ){//出力パラメータにエラーコードが含まれていないか、チェックしています。
					//return (array)$output;
					//サンプルでは、エラーが発生していた場合、エラー画面を表示して終了します。
					require_once( '/home/sample-office/www/op/src/sample/display/EntryExecError.php');
					exit;
					
				} else if( $output->isTdSecure() ){//決済実行の場合、3Dセキュアフラグをチェックします。
					

					//3Dセキュアフラグがオンである場合、リダイレクトページを表示する必要があります。
					//サンプルでは、モジュールタイプに標準添付されるリダイレクトユーティリティを利用しています。
					
					//リダイレクト用パラメータをインスタンス化して、パラメータを設定します
					require_once( '/home/sample-office/www/op/src/com/gmo_pg/client/input/AcsParam.php');
					require_once( '/home/sample-office/www/op/src/com/gmo_pg/client/common/RedirectUtil.php');
					$redirectInput = new AcsParam();
					$redirectInput->setAcsUrl( $output->getAcsUrl() );
					$redirectInput->setMd( $output->getAccessId() );
					$redirectInput->setPaReq( $output->getPaReq() );
					$redirectInput->setTermUrl( PGCARD_SAMPLE_URL . '/SecureTran.php');
					
					return $output;
					//リダイレクトページ表示クラスをインスタンス化して実行します。
//					$redirectShow = new RedirectUtil();
//					print ($redirectShow->createRedirectPage( PGCARD_SECURE_RIDIRECT_HTML , $redirectInput ) );
					exit();
					
				}
				//例外発生せず、エラーの戻りもなく、3Dセキュアフラグもオフであるので、実行結果を表示します。
			}
			
		}
		return $output;

		//EntryExecTran入力・結果画面
//		require_once( BASEPATH. '../src/sample/display/EntryExecTran.php' );
	}


	// gmo キャンセ処理
	function gmo_cancel ($query, $amount) {
//		$shopId = 'tshop00027430';
//		$shopPass = '5wabpahf';
		$shopId = '9101169839653';
		$shopPass = 'znzhar7b';
		$tax = 0;
		$tdFlag = 0;
		$itemCode = null;
		$jobCd = 'VOID'; // 取り消し
		if(!empty($query)) {
			require_once '/home/sample-office/www/op/src/com/gmo_pg/client/input/AlterTranInput.php';
			require_once '/home/sample-office/www/op/src/com/gmo_pg/client/tran/AlterTran.php';

			//入力パラメータクラスをインスタンス化します
			$input = new AlterTranInput();/* @var $input AlterTranInput */

			//各種パラメータを設定します。

			$input->setShopId( $shopId );
			$input->setShopPass( $shopPass );

			$input->setAccessId( $query->access_id );
			$input->setAccessPass( $query->access_pass );

			$input->setJobCd( $jobCd );

			$input->setAmount( $amount );
			$input->setTax( $tax );
			$input->setDisplayDate( 'null' );

			//支払方法に応じて、支払回数のセット要否が異なります。
			$method = $query->method;
			$input->setMethod( $method );
			if( $method == '2' || $method == '4'){//支払方法が、分割またはボーナス分割の場合、支払回数を設定します。
				$input->setPayTimes( $query->paytimes );
			}

			//API通信クラスをインスタンス化します
			$exe = new AlterTran();/* @var $exec AlterTran */

			//パラメータオブジェクトを引数に、実行メソッドを呼びます。
			//正常に終了した場合、結果オブジェクトが返るはずです。
			$output = $exe->exec( $input );/* @var $output AlterTranOutput */

			//実行後、その結果を確認します。
			if( $exe->isExceptionOccured() ){//取引の処理そのものがうまくいかない（通信エラー等）場合、例外が発生します。

				//サンプルでは、例外メッセージを表示して終了します。
				require_once( '/home/sample-office/www/op/sample/display/Exception.php');
				exit();

			} else {

				//例外が発生していない場合、出力パラメータオブジェクトが戻ります。

				if( $output->isErrorOccurred() ){//出力パラメータにエラーコードが含まれていないか、チェックしています。

					//サンプルでは、エラーが発生していた場合、エラー画面を表示して終了します。
					require_once( '/home/sample-office/www/op/sample/display/Error.php');
					exit();

				}

				//例外発生せず、エラーの戻りもなく、3Dセキュアフラグもオフであるので、実行結果を表示します。
			}
			return $output;
		}
	}


	function gmo_change ($query, $amount) {
//		$shopId = 'tshop00027430';
//		$shopPass = '5wabpahf';
		$shopId = '9101169839653';
		$shopPass = 'znzhar7b';
		$tax = 0;
		$tdFlag = 0;
		$itemCode = null;
		$jobCd = 'CAPTURE'; // 即時売上
		if(!empty($query)) {
			require_once( '/home/sample-office/www/op/src/com/gmo_pg/client/input/ChangeTranInput.php');
			require_once( '/home/sample-office/www/op/src/com/gmo_pg/client/tran/ChangeTran.php');
			//入力パラメータクラスをインスタンス化します
			$input = new ChangeTranInput();/* @var $input ChangeTranInput */

			//各種パラメータを設定します。

			$input->setShopId( $shopId );
			$input->setShopPass( $shopPass );

			$input->setAccessId( $query->access_id );
			$input->setAccessPass( $query->access_pass );
			$input->setJobCd( $jobCd );
			$input->setAmount( $amount );
			$input->setTax( $tax );
			$input->setDisplayDate( date('ymd') );

			//API通信クラスをインスタンス化します
			$exe = new ChangeTran();/* @var $exec ChangeTran */

			//パラメータオブジェクトを引数に、実行メソッドを呼びます。
			//正常に終了した場合、結果オブジェクトが返るはずです。
			$output = $exe->exec( $input );/* @var $output ChangeTranOutput */

			//実行後、その結果を確認します。

			if ( $exe->isExceptionOccured() ){//取引の処理そのものがうまくいかない（通信エラー等）場合、例外が発生します。
				//サンプルでは、例外メッセージを表示して終了します。
				require_once( '/home/sample-office/www/op/src/sample/display/Exception.php');
				exit();
			} else {
				//例外が発生していない場合、出力パラメータオブジェクトが戻ります。
				if( $output->isErrorOccurred() ){//出力パラメータにエラーコードが含まれていないか、チェックしています。
					//サンプルでは、エラーが発生していた場合、エラー画面を表示して終了します。
					require_once( '/home/sample-office/www/op/src/sample/display/Error.php');
					exit();
				}
			}
			//例外発生せず、エラーの戻りもなく、3Dセキュアフラグもオフであるので、実行結果を表示します。
			return $output;
		}
	}


	function gmo_getErrorMessage($errorcode){
		switch($errorcode){
		case "G01D41S61": $str1 = "ワンタッチ課金エラー：同じカードを使用せずに、別のカードを使ってください";
		return('エラーコード'.$errorcode.'　:<br>'.$str1);
		case "007P68P0B": $str1 = "テストカード決済金額上限エラー：別のカードを使ってください";
		return('エラーコード'.$errorcode.'　:<br>'.$str1);
		case "007P72P0D": $str1 = "重複決済エラー：すでに同じ注文が完了しています。販売者にお問い合わせください";
		return('エラーコード'.$errorcode.'　:<br>'.$str1);
		case "007P68P09": $str1 = "決済金額下限エラー：ご指定の金額を変更するか。販売者にお問い合わせください";
		return('エラーコード'.$errorcode.'　:<br>'.$str1);
		case "G01P05P07": $str1 = "決済データ送信元IPエラー：販売者にお問い合わせください";
		return('エラーコード'.$errorcode.'　:<br>'.$str1);
		//case "G01T52G07": $str1 = "支払回数エラー：このカードは分割払い未対応です。恐れ入りますが、一括払いをお選びください。";
		//return('エラーコード'.$errorcode.'　:<br>'.$str1);
		
		}

		$str1 = "不明な決済エラー";
		$lv1 = substr($errorcode,0,3);
		$lv2 = substr($errorcode,3,3);
		$lv3 = substr($errorcode,6,3);
		switch($lv1){
		case "000": $str1 = "正常"; break;
		case "002": $str1 = "内部処理時"; break;
		case "011": $str1 = "店舗から送信されたパラメータの取得時"; break;
		case "G01":
			if($lv2=='T52' && $lv3=='G01') {
				$str1 = "カード会社事由による決済エラー：<br>カード会社にお問い合わせください。";
			} elseif($lv2=='T52' && $lv3=='G07') {
				$str1 = "支払回数エラー：<br>このカードは分割払い未対応です。<br>恐れ入りますが、一括払いをお選びください。";
			} else {
				$str1 = "ゲートウェイ処理時";
			}
			break;
		case "L01": $str1 = "リンク(フォーム)処理時(PC版)"; break;
		case "L02": $str1 = "リンク(フォーム)処理時(MB版)"; break;
		}
		switch($lv3){
		case "G01": 
		case "G13": $str1 = "カード会社事由による決済エラー：<br>カード会社にお問い合わせください。"; break;
		}
		

		//case "": $str1 = ""; break;
		return('エラーコード'.$errorcode.'　:<br>'.$str1);
	}

}

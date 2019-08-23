<?php

/**
 * Plugin Name:       Seeds
 * Plugin URI:        https://github.com/limikael/wp-seeds
 * GitHub Plugin URI: https://github.com/limikael/wp-seeds
 * Description:       Transferrable tokens.
 * Version:           0.0.1
 * Author:            Mikael Lindqvist
 * License:           GNU General Public License v2
 */

require_once __DIR__."/src/lib.php";
require_once __DIR__."/src/ConcreteListTable.php";
require_once __DIR__."/src/SeedsTransaction.php";

function seeds_accounts_page() {
	global $wpdb;

	$vars=array();

	$listTable=new ConcreteListTable();
	$listTable->addFieldColumn("Account","account");
	$listTable->addFieldColumn("Balance","balance");
	$listTable->addFieldColumn("Transactions","transactions");


	$tableName=SeedsTransaction::getFullTableName();
	$sent=$wpdb->get_results(
		"SELECT    from_user_id, COUNT(from_user_id) as cnt ".
		"FROM      $tableName ".
		"GROUP BY  from_user_id",
		OBJECT_K);

	$received=$wpdb->get_results(
		"SELECT    to_user_id, COUNT(to_user_id) as cnt ".
		"FROM      $tableName ".
		"GROUP BY  to_user_id",
		OBJECT_K);

	$vars["total"]=0;
	$users=array();
	foreach (get_users() as $user) {
		$balance=get_user_meta($user->ID,"seeds_balance",TRUE);
		if (!$balance)
			$balance=0;

		$vars["total"]+=$balance;
		$transactionAmount=intval($sent[$user->ID]->cnt)+intval($received[$user->ID]->cnt);
		$link=get_admin_url(NULL,"admin.php?page=seeds_transactions&account=".$user->ID);

		$users[]=array(
			"account"=>SeedsTransaction::formatUser($user),
			"balance"=>$balance,
			"transactions"=>"<a href='$link'>$transactionAmount</a>",
		);
	}

	$listTable->setData($users);
	$vars["table"]=$listTable;

	render_template(__DIR__."/tpl/seeds_accounts.tpl.php",$vars);
}

function seeds_create_page() {
	$vars=array();

	$vars["errorMessage"]="";
	$vars["amount"]="";
	$vars["userId"]="";

	if (isset($_REQUEST["amount"])) {
		if (!$_REQUEST["amount"])
			$vars["errorMessage"]="You need to select an amount.";

		else if (!$_REQUEST["userId"])
			$vars["errorMessage"]="You need to select an account.";

		else {
			$user=get_user_by("id",$_REQUEST["userId"]);
			$balance=intval(get_user_meta($user->ID,"seeds_balance",TRUE));
			$balance+=intval($_REQUEST["amount"]);
			update_user_meta($user->ID,"seeds_balance",$balance);

			render_template(__DIR__."/tpl/seeds_create_done.tpl.php",$vars);
			return;
		}

		$vars["amount"]=$_REQUEST["amount"];
		$vars["userId"]=$_REQUEST["userId"];
	}

	$vars["action"]=get_admin_url(NULL,"admin.php?page=seeds_create");
	$vars["users"]=array();
	foreach (get_users() as $user) {
		$vars["users"][]=array(
			"id"=>$user->ID,
			"label"=>$user->data->user_nicename."( ".$user->data->user_email.")"
		);
	}

	render_template(__DIR__."/tpl/seeds_create.tpl.php",$vars);
}

function seeds_burn_page() {
	$vars=array();

	$vars["errorMessage"]="";
	$vars["amount"]="";
	$vars["userId"]="";

	if (isset($_REQUEST["amount"])) {
		if (!$_REQUEST["amount"])
			$vars["errorMessage"]="You need to select an amount.";

		else if (!$_REQUEST["userId"])
			$vars["errorMessage"]="You need to select an account.";

		else {
			$user=get_user_by("id",$_REQUEST["userId"]);
			$balance=intval(get_user_meta($user->ID,"seeds_balance",TRUE));

			if ($_REQUEST["amount"]>$balance)
				$vars["errorMessage"]="Insufficient seeds on that account.";

			else {
				$balance-=intval($_REQUEST["amount"]);
				update_user_meta($user->ID,"seeds_balance",$balance);

				render_template(__DIR__."/tpl/seeds_burn_done.tpl.php",$vars);
				return;
			}
		}

		$vars["amount"]=$_REQUEST["amount"];
		$vars["userId"]=$_REQUEST["userId"];
	}

	$vars["action"]=get_admin_url(NULL,"admin.php?page=seeds_burn");
	$vars["users"]=array();
	foreach (get_users() as $user) {
		$vars["users"][]=array(
			"id"=>$user->ID,
			"label"=>$user->data->user_nicename."( ".$user->data->user_email.")"
		);
	}

	render_template(__DIR__."/tpl/seeds_burn.tpl.php",$vars);
}

function seeds_transaction_detail_page($transactionId) {
	$vars=array();

	$vars["transaction"]=SeedsTransaction::findOne($transactionId);

	render_template(__DIR__."/tpl/seeds_transaction_details.tpl.php",$vars);
}

function seeds_transactions_page() {
	if (isset($_REQUEST["transaction_detail"])) {
		seeds_transaction_detail_page($_REQUEST["transaction_detail"]);
		return;
	}

	$vars=array();

	$accounts=array();
	foreach (get_users() as $user)
		$accounts[$user->ID]=SeedsTransaction::formatUser($user);

	$table=new ConcreteListTable();
	$table->addFilter(array(
		"key"=>"account",
		"options"=>$accounts,
		"allLabel"=>"All Accounts"
	));

	$table->addFieldColumn("Transaction ID","id");
	$table->addFieldColumn("From Account","fromAccount");
	$table->addFieldColumn("To Account","toAccount");
	$table->addFieldColumn("Amount","amount");

	$usersById=array();
	foreach (get_users() as $user)
		$usersById[$user->ID]=$user;

	$transactionViews=array();

	if (isset($_REQUEST["account"]) && $_REQUEST["account"])
		$transactions=SeedsTransaction::findAllByQuery(
			"SELECT * ".
			"FROM   :table ".
			"WHERE  from_user_id=%s ".
			"OR     to_user_id=%s",
			$_REQUEST["account"],
			$_REQUEST["account"]);

	else
		$transactions=SeedsTransaction::findAll();

	foreach ($transactions as $transaction) {
		$fromUser=$usersById[$transaction->from_user_id];
		$toUser=$usersById[$transaction->to_user_id];

		$link=get_admin_url(NULL,"admin.php?page=seeds_transactions&transaction_detail=".$transaction->id);

		$transactionViews[]=array(
			"id"=>"<a href='$link'>".$transaction->transaction_id."</a>",
			"fromAccount"=>$fromUser->data->user_nicename." (".$fromUser->data->user_email.")",
			"toAccount"=>$toUser->data->user_nicename." (".$toUser->data->user_email.")",
			"amount"=>$transaction->amount,
		);
	}

	$table->setData($transactionViews);
	$vars["table"]=$table;

	$vars["userAccounts"]=array();
	foreach (get_users() as $user)
		$vars["userAccounts"][$user->ID]=SeedsTransaction::formatUser($user);

	render_template(__DIR__."/tpl/seeds_transactions.tpl.php",$vars);
}

function seeds_new_transaction_page() {
	$vars=array();

	if (isset($_REQUEST["amount"])) {
		if (!$_REQUEST["amount"])
			$vars["errorMessage"]="You need to enter an amount.";

		else if (!$_REQUEST["fromUserId"])
			$vars["errorMessage"]="You need to select an account to take the seeds from.";

		else if (!$_REQUEST["toUserId"])
			$vars["errorMessage"]="You need to select an account to send the seeds to.";

		else {
			$transaction=new SeedsTransaction();
			$transaction->from_user_id=$_REQUEST["fromUserId"];
			$transaction->to_user_id=$_REQUEST["toUserId"];
			$transaction->amount=$_REQUEST["amount"];
			$transaction->notice=$_REQUEST["notice"];

			try {
				$transaction->perform();
				seeds_transaction_detail_page($transaction->id);
				return;
			}

			catch (Exception $e) {
				$vars["errorMessage"]=$e->getMessage();
			}
		}

		$vars["amount"]=$_REQUEST["amount"];
		$vars["fromUserId"]=$_REQUEST["fromUserId"];
		$vars["toUserId"]=$_REQUEST["toUserId"];
		$vars["notice"]=$_REQUEST["notice"];
	}

	$vars["userAccounts"]=array();
	foreach (get_users() as $user)
		$vars["userAccounts"][$user->ID]=SeedsTransaction::formatUser($user);

	render_template(__DIR__."/tpl/seeds_new_transaction.tpl.php",$vars);

}

function seeds_options_page() {
	// check here: http://qnimate.com/wordpress-settings-api-a-comprehensive-developers-guide/

	render_template(__DIR__."/tpl/seeds_options.tpl.php");
}

function seeds_admin_menu() {
	add_menu_page("Seeds","Seeds",
		"manage_options","seeds_accounts","seeds_accounts_page",
		"dashicons-money",71);

	add_submenu_page("seeds_accounts","Seed Accounts","Accounts",
		"manage_options","seeds_accounts","seeds_accounts_page");

	add_submenu_page("seeds_accounts","Seed Transactions","Transactions",
		"manage_options","seeds_transactions","seeds_transactions_page");

	add_submenu_page("seeds_accounts","New Seed Transaction","New Transaction",
		"manage_options","seeds_new_transaction","seeds_new_transaction_page");

	add_submenu_page("seeds_accounts","Create Seeds","Create Seeds",
		"manage_options","seeds_create","seeds_create_page");

	add_submenu_page("seeds_accounts","Burn Seeds","Burn Seeds",
		"manage_options","seeds_burn","seeds_burn_page");

	add_options_page( 
		'Seeds Settings',
		'Seeds',
		'manage_options',
		'seeds_options',
		'seeds_options_page'
	);
}

add_action("admin_menu","seeds_admin_menu");

function seeds_activate() {
	SeedsTransaction::install();
}

register_activation_hook(__FILE__,"seeds_activate");
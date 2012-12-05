<?php
/* XeroCredential Fixture generated on: 
Warning: date(): It is not safe to rely on the system's timezone settings. You are *required* to use the date.timezone setting or the date_default_timezone_set() function. In case you used any of those methods and you are still getting this warning, you most likely misspelled the timezone identifier. We selected 'Pacific/Auckland' for 'NZST/12.0/no DST' instead in /Users/markhaussmann/Sites/DebtorDaddy/web/trunk/cake/console/templates/default/classes/fixture.ctp on line 24
2011-05-10 14:05:21 : 1304996181 */
class XeroCredentialFixture extends CakeTestFixture {
	var $name = 'XeroCredential';

	var $fields = array(
		'id' => array('type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'primary'),
		'organisation_id' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 36),
		'key' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 40),
		'secret' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 40),
		'session_handle' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 40),
		'expires' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'modified' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'indexes' => array('PRIMARY' => array('column' => 'id', 'unique' => 1)),
		'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'MyISAM')
	);

	var $records = array(
		array(
			'id' => 1,
			'organisation_id' => '4d47308a-edd0-439e-850a-6b74788a128d',
			'key' => 'YTQ5ZTAXZMEZYMZMNDC5ZGEXN2NKYW',
			'secret' => 'M2RKZMU2MTLINDG4NGRIMGI2ZGY4ZJ',
			'session_handle' => 'MMI2NTFKZDRJZJIZNGY0MWEXNZJMNJ',
			'expires' => NULL,
			'created' => '2011-02-01 10:58:54',
			'modified' => '2011-02-02 05:00:09'
		),
		array(
			'id' => 53,
			'organisation_id' => '4d47344d-b59c-4891-850c-6c23788a128d',
			'key' => 'MJQYNTQYZGYWNZCZNDLKOTHMNWUWY2',
			'secret' => 'MWY3NDQ1MWE4ZWY5NDBHODK1ZGZKZW',
			'session_handle' => 'YJI3ZGNMMJRMYMQ2NDMXODKYOTLHZG',
			'expires' => NULL,
			'created' => '2011-03-15 17:02:35',
			'modified' => '2011-03-16 05:00:23'
		),
		array(
			'id' => 3,
			'organisation_id' => '4d487e45-e21c-4c95-b906-3907788a128d',
			'key' => 'YZM3MTU1NZK1ODBLNDC5MMI2ZMY5MJ',
			'secret' => 'PYPNLA2CJS45N8NV0HG4WCRVGJ7CXG',
			'session_handle' => 'NGMXYTC0YTCXYTU1NDG3MDKZYJK3N2',
			'expires' => NULL,
			'created' => '2011-02-02 10:52:09',
			'modified' => '2011-02-02 10:52:09'
		),
		array(
			'id' => 4,
			'organisation_id' => '4d488467-e670-449a-8527-3a68788a128d',
			'key' => 'YZE1MWU5YME3ZGRMNGQ2M2IYNWFMNZ',
			'secret' => 'VQH5BDRODPA0LKNWBFKBIRNSTFC3XK',
			'session_handle' => 'ZMZMZJRKNJBINMQ1NDMWY2FHMMI5NT',
			'expires' => NULL,
			'created' => '2011-02-02 11:09:24',
			'modified' => '2011-02-02 11:09:24'
		),
		array(
			'id' => 5,
			'organisation_id' => '4d4886a9-93e8-4277-8c73-3a68788a128d',
			'key' => 'OWU2ZMZIOTU1MDAZNDIWYTK0ZGJLYT',
			'secret' => '2DY9ZE4EGOHGMIIH8O0SCCRWNEDLHK',
			'session_handle' => 'MGRMMDY5OWM1NTE5NDDMYJKYNGVLMG',
			'expires' => NULL,
			'created' => '2011-02-02 11:20:00',
			'modified' => '2011-02-02 11:20:00'
		),
		array(
			'id' => 118,
			'organisation_id' => '4d51dba5-9ea0-409d-9402-025e788a128d',
			'key' => 'MWY3MMNJMGE3NJI5NDAYYJHKMDNMNZ',
			'secret' => 'NDI3MMZJNJC1NZJJNGRHNJKXZGE0YJ',
			'session_handle' => 'YTEYYJIXYZNHNWI3NGE3ZTHLZGJMZJ',
			'expires' => NULL,
			'created' => '2011-04-14 11:07:49',
			'modified' => '2011-04-18 04:00:25'
		),
		array(
			'id' => 13,
			'organisation_id' => '4d4898f1-7e00-4aa6-9f34-3ee4788a128d',
			'key' => 'ODLHZDEYYWVJOGVINGQXYJK5MWFHMD',
			'secret' => 'ZTIYN2ZHNZRKOWIZNGM4ODGZNGY3ZJ',
			'session_handle' => 'MMQWMTNKMZHJYWJLNDZLOGE4OTG2OD',
			'expires' => NULL,
			'created' => '2011-02-04 14:00:53',
			'modified' => '2011-02-07 05:00:26'
		),
		array(
			'id' => 60,
			'organisation_id' => '4d4f8816-3714-4621-8352-681c788a128d',
			'key' => 'YJGZNZK4ZGU1YJEYNDU3ZGJJYTIWY2',
			'secret' => 'ZDQ4NJKYMWJHMDNLNDCXZMIWNGFHMT',
			'session_handle' => 'NDNJY2U5YJHHYJBMNDK5OWIWNJZMN2',
			'expires' => NULL,
			'created' => '2011-03-17 11:56:15',
			'modified' => '2011-03-17 11:56:15'
		),
		array(
			'id' => 32,
			'organisation_id' => '4d48a29d-d7c0-48f1-87a9-3f1c788a128d',
			'key' => 'NTI3OGFIMMNMZTKYNGU2ZGJLNWI0NW',
			'secret' => 'ZDU5NJVLNDJMMJBMNDBHMGI0MMZIYJ',
			'session_handle' => 'MDGWNWQ3MMI5MGM2NDQWZMI4Y2YYNG',
			'expires' => NULL,
			'created' => '2011-02-20 15:23:08',
			'modified' => '2011-03-17 05:00:23'
		),
		array(
			'id' => 18,
			'organisation_id' => '4d523543-6bb4-4146-b32f-1d9b788a128d',
			'key' => 'ODUYYWUZMTYYZJEWNDVHOWEWMGYZNJ',
			'secret' => 'WBXEO3HCHUI5OAM5XJGG2HHEITF0XO',
			'session_handle' => 'NZE3OTRLNGU4NDU2NDU0OWE4Y2QWNT',
			'expires' => NULL,
			'created' => '2011-02-09 19:34:44',
			'modified' => '2011-02-09 19:34:44'
		),
		array(
			'id' => 20,
			'organisation_id' => '4d523947-9bb0-46c7-b433-1ed5788a128d',
			'key' => 'ODK4Y2U1MGRINZCWNDK2NJK4MTG1YW',
			'secret' => 'OGJHYZZJNTIXNDBMNDU4YTHHYTHKZT',
			'session_handle' => 'MMU3YZRJMDRLZGU4NDRJZMJMMMYZOD',
			'expires' => NULL,
			'created' => '2011-02-09 20:22:03',
			'modified' => '2011-02-09 20:22:03'
		),
		array(
			'id' => 103,
			'organisation_id' => '4d589835-fa34-47c0-99bf-5d55788a128d',
			'key' => 'YJKXNMQ1OWY5Y2Q4NGYYNDHLNZFLZD',
			'secret' => 'ZDJJMMNIMMZIYJQXNGJLZTHJZGFHOG',
			'session_handle' => 'MMU1NDA4NZZHOGYXNDVLOGEWYTQ2NG',
			'expires' => NULL,
			'created' => '2011-04-06 21:25:52',
			'modified' => '2011-04-18 04:00:43'
		),
		array(
			'id' => 80,
			'organisation_id' => '4d5af116-2518-44dc-a7a6-0ae3788a128d',
			'key' => 'ZDM5NTQXM2ZLMDDKNDKZM2I3YWNIOW',
			'secret' => 'MDC1MJM1MJE3OTFLNGJKZJHMYTLIYJ',
			'session_handle' => 'NWVKZDRHMTK0MWMWNGYYYWI2MWMXNZ',
			'expires' => NULL,
			'created' => '2011-03-23 14:19:46',
			'modified' => '2011-03-23 14:19:46'
		),
		array(
			'id' => 27,
			'organisation_id' => '4d5b329f-d7d8-4997-8f18-1c55788a128d',
			'key' => 'MGM2MZVJODLJYTE1NDYZMMJLYJRHNT',
			'secret' => 'ACMXTVEGYPE9GF4HAF4GQR6URKMFR5',
			'session_handle' => 'OTRMZTDLMGUYZWNINDHIOGI0ZTNHNZ',
			'expires' => NULL,
			'created' => '2011-02-16 15:14:36',
			'modified' => '2011-02-16 15:14:36'
		),
		array(
			'id' => 28,
			'organisation_id' => '4d5b34b7-167c-42dc-b5c7-1dad788a128d',
			'key' => 'YZCYMWQYMJY1ZTJHNDGZMDK4NWZLYT',
			'secret' => 'OTAWMJIZNWI1Y2E1NDUYNDHMNGQ1ZJ',
			'session_handle' => 'MWNKZJGWNDDMYTG3NDZIN2E2NDM4YM',
			'expires' => NULL,
			'created' => '2011-02-16 15:24:11',
			'modified' => '2011-03-26 05:02:36'
		),
		array(
			'id' => 29,
			'organisation_id' => '4d5c584a-f93c-413a-8184-6d7f788a128d',
			'key' => 'MZBIOGY2ZDAXNTCZNDZKYME1YMI3YT',
			'secret' => 'ZDKYNZRJMWVLMGVKNDRKODLJNJEWOD',
			'session_handle' => 'NWE3YJE4NJVIYMY0NDNJYWIYN2Y4NJ',
			'expires' => NULL,
			'created' => '2011-02-17 12:07:29',
			'modified' => '2011-03-28 05:00:48'
		),
		array(
			'id' => 40,
			'organisation_id' => '4d604912-9134-46ac-b70d-7202788a128d',
			'key' => 'MZZKNZAYMGEXZJDHNGIZMMFJNDBHOD',
			'secret' => 'MZAYOWE1MGY5OTRKNGZMMZG3OWUWOD',
			'session_handle' => 'NZU4YJZKZTY4ZDCWNDIZMGJHNWE0ZJ',
			'expires' => NULL,
			'created' => '2011-03-02 22:33:10',
			'modified' => '2011-04-18 04:01:06'
		),
		array(
			'id' => 104,
			'organisation_id' => '4d8bdd1c-ae3c-4d2c-86e9-3385788a128d',
			'key' => 'NJKZODJJZTQ0NDE2NDK4MJLKNTEXM2',
			'secret' => 'ZGIWY2NLMZAZZWE1NDLMNDHHMMQ0NJ',
			'session_handle' => 'YZGXZGE4M2YYOGNJNDIXMMJMMMQYNW',
			'expires' => NULL,
			'created' => '2011-04-07 14:37:15',
			'modified' => '2011-04-18 04:00:54'
		),
		array(
			'id' => 124,
			'organisation_id' => '4d794681-e060-4e7f-a6c7-770f788a128d',
			'key' => 'MDBLNMFHNDFHZMEYNDGYMTLHODCYYM',
			'secret' => 'YWYZZDFKYMFJNJDINGIYNTG1M2I4ZW',
			'session_handle' => 'NJU1NGM1YJVKOTKWNDYWYTHIMWFKNM',
			'expires' => NULL,
			'created' => '2011-04-18 13:18:59',
			'modified' => '2011-04-18 13:18:59'
		),
		array(
			'id' => 61,
			'organisation_id' => '4d814a12-8800-40fe-b207-16c9788a128d',
			'key' => 'MDY2MGI3YZUXN2UYNDC5ZMJMZWFJMJ',
			'secret' => 'HBHJ1DQOWYTNQRSERCPPYB9KTNFA6V',
			'session_handle' => 'ZTZIZJI2MMMXM2JHNGYZZGI3ZTY0YT',
			'expires' => NULL,
			'created' => '2011-03-17 12:40:09',
			'modified' => '2011-03-17 12:40:09'
		),
		array(
			'id' => 78,
			'organisation_id' => '4d815de9-a334-40eb-808a-1b40788a128d',
			'key' => 'MGNMYWMYMTEYMTM4NDM2MZK2NTRKMJ',
			'secret' => 'MZCZZJM4YTVLMTNHNDNLNDG2YZC0OT',
			'session_handle' => 'OWQWMWI4MTKXMME4NGYZZWJLNJU0ND',
			'expires' => NULL,
			'created' => '2011-03-22 12:26:18',
			'modified' => '2011-04-04 14:04:14'
		),
		array(
			'id' => 125,
			'organisation_id' => '4d815f83-0e78-45b1-be41-1b25788a128d',
			'key' => 'ZDM0MTU2NDLIYJVLNGQ4YWFHY2JIOT',
			'secret' => 'M2I2MTYXOGJINWYXNDI0MZG5NZMZNZ',
			'session_handle' => 'NZMXZDI4ZJU2ZTBJNDJJZJG2ZDI2ZJ',
			'expires' => NULL,
			'created' => '2011-04-18 16:06:19',
			'modified' => '2011-04-18 16:06:19'
		),
		array(
			'id' => 91,
			'organisation_id' => '4d9507f4-9ff8-4646-9146-19a9788a128d',
			'key' => 'YTI0NJEYNJGYMGJMNGY5YJHHOGJLOG',
			'secret' => 'YTK1ZJA4MDMYODHKNGQXY2EXZDQYNW',
			'session_handle' => 'ZGM5YTBJMMNIM2RKNGIYNTG2MWU4OT',
			'expires' => NULL,
			'created' => '2011-04-01 12:03:20',
			'modified' => '2011-04-18 04:01:33'
		),
		array(
			'id' => 109,
			'organisation_id' => '4da2707c-0de0-4171-8ed5-39bf788a128d',
			'key' => 'MJHINTLJZMEZMGE3NDFINTK1MWVKZJ',
			'secret' => 'NTKYZDU3MDHIOGE0NDM0ZGFMY2UYYT',
			'session_handle' => 'MDK3MDI5ZTK2YMFINGM5MZHHZTLKY2',
			'expires' => NULL,
			'created' => '2011-04-11 15:09:47',
			'modified' => '2011-04-18 04:01:23'
		),
		array(
			'id' => 123,
			'organisation_id' => '4da7d156-2fdc-49cf-997a-51af788a128d',
			'key' => 'YJQWMTU5ZTKWYTAZNDYZMJLMNTDLZD',
			'secret' => 'N2U5ODU1MDRLYJRINDDHYTLJMWQ2YJ',
			'session_handle' => 'MZMZNZKXYJRIZGNJNDZIN2EXNDLHNZ',
			'expires' => NULL,
			'created' => '2011-04-15 17:03:19',
			'modified' => '2011-04-18 04:01:52'
		),
	);
}
?>
<?php
/**
 * Licensing Functionality
 * Last Changed: $LastChangedDate: 2017-05-31 23:14:46 -0400 (Wed, 31 May 2017) $
 * @author detain
 * @version $Revision: 24994 $
 * @copyright 2017
 * @package MyAdmin
 * @category Licenses
 */

/**
 * unbilled_cpanel()
 *
 * @return void
 */
function unbilled_cpanel() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('view_service')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	$db = get_module_db('licenses');
	$db_vps = get_module_db('vps');
	$db_vps2 = get_module_db('vps');
	$db_innertell = get_module_db('innertell');
	$db_cms = get_module_db('mb');
	$type = SERVICE_TYPES_CPANEL;
	if (!isset($GLOBALS['webpage']) || $GLOBALS['webpage'] != false) {
		page_title('Unbilled CPanel Licenses');
		if (class_exists('TFTable')) {
			$out_type = 'tftable';
			$table = new TFTable;
			$table->alternate_rows();
		} else
			$out_type = 'table';
	} else
		$out_type = 'text';
	$serviceTypes = get_license_types();
	$dir = __DIR__;
	//209.159.155.230,4893465,Printnow.Gr,Linux,centos enterprise 5.8,11.32.3.19,virtuozzo,2.6.18-238.19.1.el5.028stab092.2PAE,INTERSERVER-INTERNAL-VZZO,1
	$whitelist = explode("\n", trim(`cat /home/interser/public_html/misha/cpanel_whitelist.txt`));
	$licenses = [];
	$tocheck = [];
	$good_ips = [];
	$ip_output = [];
	$cpl = new \Detain\Cpanel\Cpanel(CPANEL_LICENSING_USERNAME, CPANEL_LICENSING_PASSWORD);
	$status = $cpl->fetchLicenses();
	foreach ($status['licenses'] as $key => $license2) {
		$license = [];
		$license['ip'] = $license2['ip'];
		$license['liscid'] = $license2['licenseid'];
		$license['hostname'] = $license2['hostname'];
		$license['os'] = $license2['os'];
		$license['distro'] = $license2['distro'];
		$license['version'] = $license2['version'];
		$license['envtype'] = $license2['envtype'];
		$license['osver'] = $license2['osver'];
		$license['package'] = $license2['packageid'];
		$license['status'] = $license2['status'];
		$licenses[$license['ip']] = $license;
		if (in_array($license['ip'], $whitelist)) {
			$good_ips[] = $license['ip'];
			continue;
		}
		$tocheck[$license['ip']] = $license;
	}
	$db->query("select location.primary_ipv4 from servers left join location on location.order_id=servers.server_id where servers.server_status='active' and location.primary_ipv4 is not null and (servers.server_dedicated_tag like '%,%,%,%,%,%,%,6,%' or servers.server_dedicated_tag like '%,%,%,%,%,%,%,1,%' or servers.server_dedicated_cp=1 or servers.server_dedicated_cp=6) and location.primary_ipv4 in ('" . implode("','", array_keys($tocheck)) . "')", __LINE__, __FILE__);
	while ($db->next_record(MYSQL_ASSOC)) {
		$good_ips[] = $db->Record['primary_ipv4'];
		unset($tocheck[$db->Record['primary_ipv4']]);
	}
	$db->query("select services_field1, services_name from services where services_module='licenses'");
	$services = [];
	while ($db->next_record(MYSQL_ASSOC)) {
		$services[$db->Record['services_field1']] = $db->Record['services_name'];
	}
	/*
	$db->query("select license_ip from licenses left join services on services_id=license_type where services_category=1 and license_status='active' and license_ip in ('" . implode("','", array_keys($tocheck)) . "')", __LINE__, __FILE__);
	while ($db->next_record(MYSQL_ASSOC)) {
	unset($tocheck[$db->Record['license_ip']]);
	}
	*/
	/*
	$db_vps->query("select vps_ip from vps, repeat_invoices where vps_status='active' and concat('CPanel for VPS ', vps.vps_id)=repeat_invoices.repeat_invoices_description and vps_ip in ('" . implode("','", array_keys($tocheck)) . "')");
	while ($db_vps->next_record(MYSQL_ASSOC)) {
	unset($tocheck[$db_vps->Record['vps_ip']]);
	}
	*/
	foreach ($tocheck as $ip => $license) {
		if (!isset($ip_output[$license['ip']])) {
			$ip_output[$license['ip']] = [];
		}
		$db_cms->query("select * from client_package, package_type where client_package.pack_id=package_type.pack_id and cp_comments like '%{$license['ip']}%' and pack_name like '%Cpanel%' and cp_status=2");
		if ($db_cms->num_rows() > 0) {
			$good_ips[] = $license['ip'];
		}
		if (!in_array($license['ip'], $good_ips)) {
			$db->query("select licenses.*, services_name, services_field1 from licenses left join services on services_id=license_type where license_ip='{$license['ip']}' and services_category={$type}");
			if ($db->num_rows() > 0) {
				while ($db->next_record(MYSQL_ASSOC)) {
					//$url = 'https://cpaneldirect.net/index.php?choice=none.view_license&id=' . $db->Record['license_id'] . '&sessionid=' . $session_id;
					$url = false;
					if ($db->Record['license_status'] == 'active' && $db->Record['services_field1'] == $license['package']) {
						$good_ips[] = $license['ip'];
					} elseif ($db->Record['license_status'] != 'active' && $db->Record['services_field1'] == $license['package']) {
						$ip_output[$license['ip']][] = 'CPanelDirect License ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_license&id=' . $db->Record['license_id']) . '" target=_blank>' . $db->Record['license_id'] . '</a>' . ' Found but status is ' . $db->Record['license_status'];
						// $db->query("update licenses set license_type=$license_type where license_id='{$db->Record['license_id']}'");
					} elseif ($db->Record['license_status'] == 'active' && $db->Record['services_field1'] != $license['package']) {
						$ip_output[$license['ip']][] = 'CPanelDirect License ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_license&id=' . $db->Record['license_id']) . '" target=_blank>' . $db->Record['license_id'] . '</a>' . ' Found but type is ' . str_replace('INTERSERVER-', '', $db->Record['services_name']) . ' instead of ' . str_replace('INTERSERVER-', '', $services[$license['package']]);
					} else {
						$ip_output[$license['ip']][] = 'CPanelDirect License ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_license&id=' . $db->Record['license_id']) . '" target=_blank>' . $db->Record['license_id'] . '</a>' . ' Found but status is ' . $db->Record['license_status'] . ' and type is ' . str_replace('INTERSERVER-', '', $db->Record['services_name']) . ' instead of ' . str_replace('INTERSERVER-', '', $services[$license['package']]);
					}
				}
			}
		}
		if (!in_array($license['ip'], $good_ips)) {
			$db_vps->query("select * from vps left join repeat_invoices on concat('CPanel for VPS ', vps.vps_id) = repeat_invoices.repeat_invoices_description where vps_ip='{$license['ip']}'");
			if ($db_vps->num_rows() > 0) {
				while ($db_vps->next_record()) {
					$vps = $db_vps->Record;
					if ($vps['vps_status'] == 'active' && $vps['repeat_invoices_id'] != null) {
						$db_vps2->query(
							'select * from invoices where invoices_extra=' . $vps['repeat_invoices_id'] . " and invoices_type=1 and invoices_paid=1 and invoices_date >= date_sub('" . mysql_now() . "', INTERVAL " .
							(1 + $vps['repeat_invoices_frequency']) . ' MONTH)'
						);
						if ($db_vps2->num_rows() > 0) {
							$good_ips[] = $license['ip'];
						} else {
							$ip_output[$license['ip']][] = 'VPS ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_vps&id=' . $vps['vps_id']) . '" target=_blank>' . $vps['vps_id'] . '</a>' . ' Has Cpanel But Has not Paid In 2+ Months';
						}
					} elseif ($vps['vps_status'] == 'active' && $vps['repeat_invoices_id'] == null) {
						$ip_output[$license['ip']][] = 'VPS ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_vps&id=' . $vps['vps_id']) . '" target=_blank>' . $vps['vps_id'] . '</a>' . ' Found but no CPanel';
					} elseif ($vps['vps_status'] != 'active' && $vps['repeat_invoices_id'] != null) {
						$ip_output[$license['ip']][] = 'VPS ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_vps&id=' . $vps['vps_id']) . '" target=_blank>' . $vps['vps_id'] . '</a>' . ' Found with CPanel but VPS status is ' . $vps['vps_status'];
					} else {
						$ip_output[$license['ip']][] = 'VPS ' . '<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.view_vps&id=' . $vps['vps_id']) . '" target=_blank>' . $vps['vps_id'] . '</a>' . ' Found But Status ' . $vps['vps_status'] . ' and no CPanel';
					}
				}
			}
		}
		if (!in_array($license['ip'], $good_ips)) {
			$db->query("select vlans_comment from ips, vlans where ips_ip='$license[ip]' and ips_vlan=vlans_id");
			if ($db->num_rows() > 0) {
				$db->next_record(MYSQL_ASSOC);
				$server = str_replace(array('append ', 'Append '), array('', ''), trim($db->Record['vlans_comment']));
				$db->query("select * from servers where server_hostname like '%$server%' order by server_status");
				if ($db->num_rows() > 0) {
					$db->next_record(MYSQL_ASSOC);
					$dedicated_tag = explode(',', $db->Record['server_dedicated_tag']);
					if ($db->Record['server_username'] == 'john@interserver.net') {
						if ((sizeof($dedicated_tag) > 8 && ($dedicated_tag[7] == 1 || $dedicated_tag[7] == 6)) || $db->Record['server_dedicated_cp'] == 1 || $db->Record['server_dedicated_cp'] == 6) {
							$good_ips[] = $license['ip'];
						} else {
							$ip_output[$license['ip']][] = 'Used By ' . $db->Record['server_hostname'];
						}
					} elseif ($db->Record['server_status'] == 'active') {
						if ((sizeof($dedicated_tag) > 8 && ($dedicated_tag[7] == 1 || $dedicated_tag[7] == 6)) || $db->Record['server_dedicated_cp'] == 1 || $db->Record['server_dedicated_cp'] == 6) {
							$good_ips[] = $license['ip'];
						} else {
							$ip_output[$license['ip']][] = 'Innertell Order ' . '<a href="' . $GLOBALS['tf']->link('view_order.php', 'id=' . $db->Record['id']) . '">' . $db->Record['id'] . '</a>' . ' found but no CPanel';
						}
					} else {
						if ((sizeof($dedicated_tag) > 8 && ($dedicated_tag[7] == 1 || $dedicated_tag[7] == 6)) || $db->Record['server_dedicated_cp'] == 1 || $db->Record['server_dedicated_cp'] == 6) {
							$ip_output[$license['ip']][] = 'Innertell Order ' . '<a href="' . $GLOBALS['tf']->link('view_order.php', 'id=' . $db->Record['id']) . '" target=_blank>' . $db->Record['id'] . '</a>' . ' found but status ' . $db->Record['server_status'];
						} else {
							$ip_output[$license['ip']][] = 'Innertell Order ' . '<a href="' . $GLOBALS['tf']->link('view_order.php', 'id=' . $db->Record['id']) . '" target=_blank>' . $db->Record['id'] . '</a>' . ' found but status ' . $db->Record['server_status'] . ' and no CPanel';
						}
					}
				} else {
					$ip_output[$license['ip']][] = 'VLAN for ' . $server . ' found but no orders match';
				}
			}
		}
	}
	if ($out_type == 'table')
		add_output('<table border=1>');
	elseif ($out_type == 'tftable')
		$table->set_title('Unbilled CPanel Licenses');
	else
		echo "Unbilled CPanel Licenses\n";
	$errors = 0;
	foreach ($tocheck as $ip => $license) {
		if (!in_array($ip, $good_ips)) {
			$errors++;
			if ($out_type == 'table')
				add_output('<tr style="vertical-align: top;"><td>
				<a href="search.php?comments=no&search=' . $ip . '&expand=1" target=_blank>' . $ip . '</a>
				(<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.deactivate_cpanel&ip=' . $ip) . '" target=_blank>cancel</a>)</td>
				<td>' . $license['hostname'] . '</td><td>' . str_replace(array('INTERSERVER-', ' License'), array('', ''), $services[$license['package']]) . '</td><td>'
				);
			elseif ($out_type == 'tftable') {
				$table->set_col_options('style="width: 210px;"');
				$table->add_field('<a href="search.php?comments=no&search=' . $ip . '&expand=1" target=_blank>' . $ip . '</a> (<a href="' . $GLOBALS['tf']->link('index.php', 'choice=none.deactivate_cpanel&ip=' . $ip) . '" target=_blank>cancel</a>)', 'r');
				$table->set_col_options('');
				//					$table->set_col_options('style="width: 225px;"');
				$table->add_field($license['hostname'], 'r');
				$table->set_col_options('style="min-width: 135px; max-width: 150px;"');
				$table->add_field(str_replace(array('INTERSERVER-', ' License'), array('', ''), $services[$license['package']]), 'r');
				$table->set_col_options('style="min-width: 350px;"');
			} else
				echo "$ip	" . $license['hostname'] . '	' . str_replace(array('INTERSERVER-', ' License'), array('', ''), $services[$license['package']]) . '	';
			if (sizeof($ip_output[$ip]) > 0)
				if ($out_type == 'table')
					add_output(implode('<br>', $ip_output[$ip]));
				elseif ($out_type == 'tftable')
					$table->add_field(implode('<br>', $ip_output[$ip]), 'r');
				else
					echo strip_tags(implode(', ', $ip_output[$ip]));
			elseif ($out_type == 'table')
					add_output("I was unable to find this IP {$ip} anywhere.");
				elseif ($out_type == 'tftable')
					$table->add_field("I was unable to find this IP {$ip} anywhere.", 'r');
				else
					echo "I was unable to find this IP {$ip} anywhere.";
			if ($out_type == 'table')
				add_output('</td></tr>');
			elseif ($out_type == 'tftable')
				$table->add_row();
			else
				echo "\n";
		}
	}
	if ($out_type == 'table') {
		add_output('<tr><td colspan=4 align=center>' . $errors . '/' . sizeof($licenses) . ' Licenses have matching problems</td></tr></table>');
		add_output('</body></html>');
	} elseif ($out_type == 'tftable') {
		$table->set_colspan(4);
		$table->add_field($errors . '/' . sizeof($licenses) . ' Licenses have matching problems');
		$table->add_row();
		add_output($table->get_table());
		add_output('</body></html>');
	} else
		echo $errors . '/' . sizeof($licenses) . " Licenses have matching problems\n";
	//echo $GLOBALS['output'];
}

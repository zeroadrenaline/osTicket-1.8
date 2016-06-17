<?php
// Includes
require('staff.inc.php');



// Functions
function formatTime($time) {
	$hours = floor($time / 60);
	$minutes = $time % 60;

	$formatted = '';

	if ($hours > 0) {
		$formatted .= $hours . ' Hour';
		if ($hours > 1) {
			$formatted .= 's';
		}
	}
	if ($minutes > 0) {
		if ($formatted) $formatted .= ', ';
			$formatted .= $minutes . ' Minute';
		if ($minutes > 1) {
			$formatted .= 's';
		}
	}
	return $formatted;
}

function convTimeType($typeid) {
	$sql = 'SELECT `value` FROM `ost_list_items` WHERE `id` = '. $typeid;
	$res = db_query($sql);
	
	$typearray = db_fetch_array($res);

	$typetext = $typearray['value'];
	return $typetext;
}

function countTime($ticketid, $typeid) {
	$sql = 'SELECT SUM(`time_spent`) AS `totaltime` FROM `ost_ticket_thread` WHERE `ticket_id` = '. $ticketid .' AND `time_type` = '. $typeid .' AND time_bill = 1';
	$res = db_query($sql);
	
	$timearray = db_fetch_array($res);

	$totaltime = $timearray['totaltime'];
	return $totaltime;
}



// Generate information for display
//--Get Organisation Details
$org = null;
$org = Organization::lookup($_REQUEST['orgid']);

//--Collect Time Items
/* Determine ID value for time-type so we can look up the different time types */
	$sql = 'SELECT * FROM `ost_list` WHERE `type` = "time-type"';
	$res = db_query($sql);
	$timelist = db_fetch_array($res);
	$timelistid = $timelist['id'];
	
/* Generate Array of times times for summary */
	$sql = 'SELECT * FROM `ost_list_items` where `list_id` = ' . $timelistid;
	$timetyperes = db_query($sql);
	$loop = 0;
	while($timerow = db_fetch_array($timetyperes, MYSQLI_ASSOC)) {
		$loop++;
		$time[$loop] = $timerow['id'];
	}

//--Generate SQL
/* SELECT statements to gather all the required information */
$select ='SELECT ticket.ticket_id,ticket.`number`,ticket.dept_id,ticket.staff_id,ticket.team_id, ticket.user_id '
        .' ,dept.dept_name,status.name as status,ticket.source,ticket.isoverdue,ticket.isanswered,ticket.created '
        .' ,CAST(GREATEST(IFNULL(ticket.lastmessage, 0), IFNULL(ticket.reopened, 0), ticket.created) as datetime) as effective_date '
        .' ,CONCAT_WS(" ", staff.firstname, staff.lastname) as staff, team.name as team '
        .' ,IF(staff.staff_id IS NULL,team.name,CONCAT_WS(" ", staff.lastname, staff.firstname)) as assigned '
        .' ,IF(ptopic.topic_pid IS NULL, topic.topic, CONCAT_WS(" / ", ptopic.topic, topic.topic)) as helptopic '
        .' ,cdata.priority as priority_id, cdata.subject, user.name, email.address as email';

/* FROM statement specifying where the data is coming from */
$from =' FROM '.TICKET_TABLE.' ticket '
      .' LEFT JOIN '.TICKET_STATUS_TABLE.' status
        ON status.id = ticket.status_id '
      .' LEFT JOIN '.USER_TABLE.' user ON user.id = ticket.user_id '
      .' LEFT JOIN '.USER_EMAIL_TABLE.' email ON user.id = email.user_id '
      .' LEFT JOIN '.USER_ACCOUNT_TABLE.' account ON (ticket.user_id=account.user_id) '
      .' LEFT JOIN '.DEPT_TABLE.' dept ON ticket.dept_id=dept.dept_id '
      .' LEFT JOIN '.STAFF_TABLE.' staff ON (ticket.staff_id=staff.staff_id) '
      .' LEFT JOIN '.TEAM_TABLE.' team ON (ticket.team_id=team.team_id) '
      .' LEFT JOIN '.TOPIC_TABLE.' topic ON (ticket.topic_id=topic.topic_id) '
      .' LEFT JOIN '.TOPIC_TABLE.' ptopic ON (ptopic.topic_id=topic.topic_pid) '
      .' LEFT JOIN '.TABLE_PREFIX.'ticket__cdata cdata ON (cdata.ticket_id = ticket.ticket_id) '
      .' LEFT JOIN '.PRIORITY_TABLE.' pri ON (pri.priority_id = cdata.priority)';

/* WHERE statement which limits the information to what is actually required / meets billing parameters */
$where = 'WHERE';
$where .= ' user.org_id = '.db_input($org->getId());
$where .= ' AND ticket.status_id = 3';
$where .= ' AND (ticket.created BETWEEN "'.$_REQUEST['startdate'].'" AND "'.$_REQUEST['enddate'].'")';
// with ticket.status_id 1 = open, 3 = closed

/* builds the actual query from the above sections */
$query ="$select $from $where ORDER BY ticket.created DESC";

//--Fetch the results
$results = array(); /* Create an array to store the information */
$res = db_query($query); /* Run query and store results in $res */
while ($row = db_fetch_array($res))
    $results[$row['ticket_id']] = $row;
/*	$results[X] = $row */

if ($results) { /* Checks to make sure tickets found before finding all threads */
    $counts_sql = 'SELECT ticket.ticket_id,
        count(DISTINCT attach.attach_id) as attachments,
        count(DISTINCT thread.id) as thread_count,
        count(DISTINCT collab.id) as collaborators
        FROM '.TICKET_TABLE.' ticket
        LEFT JOIN '.TICKET_ATTACHMENT_TABLE.' attach ON (ticket.ticket_id=attach.ticket_id) '
     .' LEFT JOIN '.TICKET_THREAD_TABLE.' thread ON ( ticket.ticket_id=thread.ticket_id) '
     .' LEFT JOIN '.TICKET_COLLABORATOR_TABLE.' collab
            ON ( ticket.ticket_id=collab.ticket_id) '
     .' WHERE ticket.ticket_id IN ('.implode(',', db_input(array_keys($results))).')
        GROUP BY ticket.ticket_id';
    $ids_res = db_query($counts_sql);
    while ($row = db_fetch_array($ids_res)) {
        $results[$row['ticket_id']] += $row;
		/* Adds thread information to the array via the ticket ID */
    }
}



// Display
//--Navigation & Page Info
$nav->setTabActive('users');
$ost->setPageTitle(sprintf(__('%s - Bill / Invoice'),$org->getName()));

require_once(STAFFINC_DIR.'header.inc.php');
/*
	Page content to be placed below here
*/
?>

<h1>Bill / Invoice</h1>
<b>Organistation:</b> <?php echo $org->getName(); ?><br />
<b>Billing Period:</b> <?php echo $_REQUEST['startdate']; ?> - <?php echo $_REQUEST['enddate']; ?><br /><br />
<h2>Labour / Time Details</h2>

<?php if ($results) { ?>
 <table class="list" border="0" cellspacing="1" cellpadding="2" width="940">
    <thead>
        <tr>
            <th width="70"><?php echo __('Ticket'); ?></th>
            <th width="100"><?php echo __('Date'); ?></th>
            <th width="100"><?php echo __('Status'); ?></th>
            <th width="300"><?php echo __('Subject'); ?></th>
            <th width="400"><?php echo __('Time Summary'); ?></th>
        </tr>
    </thead>
    <tbody>
	<?php
    foreach($results as $row) {
		$status = ucfirst($row['status']);
        $tid=$row['number'];
        $subject = Format::htmlchars(Format::truncate($row['subject'],40));
        $threadcount=$row['thread_count'];
		?>
		
		<tr id="<?php echo $row['ticket_id']; ?>">
            <td align="center" nowrap>
              <a class="Icon <?php echo strtolower($row['source']); ?>Ticket ticketPreview"
                title="<?php echo __('Preview Ticket'); ?>"
                href="tickets.php?id=<?php echo $row['ticket_id']; ?>"><?php echo $tid; ?></a></td>
            <td align="center" nowrap><?php echo Format::db_datetime($row['effective_date']); ?></td>
            <td><?php echo $status; ?></td>
            <td><a <?php if ($flag) { ?> class="Icon <?php echo $flag; ?>Ticket" title="<?php echo ucfirst($flag); ?> Ticket" <?php } ?>
                href="tickets.php?id=<?php echo $row['ticket_id']; ?>"><?php echo $subject; ?></a>
                 <?php
                    if ($threadcount>1)
                        echo "<small>($threadcount)</small>&nbsp;".'<i
                            class="icon-fixed-width icon-comments-alt"></i>&nbsp;';
                    if ($row['collaborators'])
                        echo '<i class="icon-fixed-width icon-group faded"></i>&nbsp;';
                    if ($row['attachments'])
                        echo '<i class="icon-fixed-width icon-paperclip"></i>&nbsp;';
                ?>
            </td>
            <td>
				<?php
					$loop = 0;
					$timerow = "";
					for ($x = 1; $x <= count($time); $x++) {
						if (countTime($row['ticket_id'], $time[$x]) <> "" && $time[$x] > 0) {
							echo formatTime(countTime($row['ticket_id'], $time[$x])) . " " . convTimeType($time[$x]) . "<br />";
						}
					}
				?>
			</td>
        </tr>
   <?php
		$hwsql = 'SELECT * FROM `ost_ticket_hardware` WHERE `ticket_id` = ' . $row['ticket_id'];
		$hwres = db_query($hwsql);
		$loop = 0;
		while($hwrow = db_fetch_array($hwres, MYSQLI_ASSOC)) {
			$loop++;
			$hw[$loop][0] = $hwrow['description'];
			$hw[$loop][1] = $hwrow['qty'];
			$hw[$loop][2] = $hwrow['unit_cost'];
			$hw[$loop][3] = $hwrow['total_cost'];
		}
    ?>
<?php	} // END foreach $results as $row ?>
    </tbody>
</table>

<?php
} else {
	echo '<p>No tickets found</p>';
}
?>
<p>&nbsp;</p>
<h2>Hardware Details</h2>
<table class="list" border="0" cellspacing="1" cellpadding="2" width="940">
	<tr>
		<th>Ticket Number</th><th>Qty</th><th>Description</th><th>Unit Cost (Ex VAT)</th><th>Total Cost (Ex VAT)</th>
	</tr>
	<?php
		for ($x = 1; $x <= count($hw); $x++) {
			if ($hw[$x][0] <> "" && $hw[$x][0] > 0) {
				echo '<tr>';
					echo '<td>'. $hw[$x][0] .'</td>';
					echo '<td>'. $hw[$x][1] .'</td>';
					echo '<td>'. $hw[$x][2] .'</td>';
					echo '<td>'. $hw[$x][3] .'</td>';
				echo '</tr>';
			}
		}
	?>
</table>

<?php
/*
	Page content to be placed above here
*/
require_once(STAFFINC_DIR.'footer.inc.php');
?>
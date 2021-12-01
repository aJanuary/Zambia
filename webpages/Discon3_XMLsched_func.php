<?php
//	Copyright (c) 2021 Peter Olszowka. All rights reserved. See copyright document for more details.
    require_once('db_functions.php');
	function retrieveD3XMLDataSched() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$results = array();
		if (prepare_db_and_more() === false) {
			$results["message_error"] = "Unable to connect to database.<br />No further execution possible.";
			return $results;
			};
		$ConStartDatim = CON_START_DATIM;
		// first query: which people are on which sessions
		$query = <<<EOD
SELECT DISTINCT
	SCH.sessionid, POS.badgeid, POS.moderator
FROM Schedule SCH
JOIN Sessions S USING (sessionid)
JOIN ParticipantOnSession POS USING (sessionid)
WHERE
	S.pubstatusid = 2 /* Public */
ORDER BY
	SCH.sessionid,
	POS.moderator DESC,
	POS.badgeid;
EOD;
		$result = mysqli_query_with_error_handling($query);
		$participantOnSession = array();
		$cursessionid = -99999;
		$pos = array();
		while($row = mysqli_fetch_assoc($result)) {
			$sessionid = $row["sessionid"];
			if ($sessionid != $cursessionid) {
                if ($cursessionid > 0) {
                    $participantOnSession[$cursessionid] = $pos;
					$pos = array();
                }
                $cursessionid = $sessionid;
            }
			$pos[$row["badgeid"]] = $row["moderator"] == "1" ? " (moderator)" : "";
        }
		$participantOnSession[$cursessionid] = $pos;
		mysqli_free_result($result);

		// second query -  session tags
		$query = <<<EOD
SELECT DISTINCT sessionid, tagname
FROM SessionHasTag s
JOIN Tags t USING (tagid)
ORDER BY sessionid, tagname;
EOD;
		$result = mysqli_query_with_error_handling($query);
		$sessiontags = array();
		$cursessionid = -99999;
		$tags = array();
		while($row = mysqli_fetch_assoc($result)) {
			$sessionid = $row["sessionid"];
			if ($sessionid != $cursessionid) {
                if ($cursessionid > 0) {
                    $sessiontags[$cursessionid] = $tags;
					$tags = array();
                }
                $cursessionid = $sessionid;
            }
			$tags[] = $row["tagname"];
        }
		$sessiontags[$cursessionid] = $tags;
		mysqli_free_result($result);

		// third query - Session data - output as XML
		$query = <<<EOD
SELECT
	s.sessionid, s.title, s.progguidhtml as description, s.meetinglink, s.duration,
	DATE_FORMAT(ADDTIME('$ConStartDatim',sch.starttime),'%Y-%m-%d %H:%i:%s') as date,
	r.roomname, t.typename, IFNULL(f.featurename, 'On Site Only') AS virtualacc
FROM Sessions s
JOIN Schedule sch USING (sessionid)
JOIN Rooms r USING (roomid)
JOIN Types t USING (typeid)
LEFT OUTER JOIN SessionHasFeature sf ON (sf.sessionid = s.sessionid and sf.featureid in (20,21,22))
LEFT OUTER JOIN Features f USING (featureid)
WHERE pubstatusid = 2
ORDER BY starttime, title, roomname;
EOD;
		$result = mysqli_query_with_error_handling($query);
		echo "<Sessions>\n";
		while($row = mysqli_fetch_assoc($result)) {
			$session = $row["sessionid"];
			$title = $row["title"];
			$description = $row["description"];
			$date = strtotime($row["date"]);
			$roomname = $row["roomname"];
			$typename = $row["typename"];
			$meetinglink = $row["meetinglink"];
			$virtualacc = $row["virtualacc"];
			echo <<<EOD
<Session>
<ID>$session</ID>
<Title><![CDATA[$title]]></Title>
<Content><![CDATA[$description]]></Content>
<Starttime>$date</Starttime>
<Location><![CDATA[$roomname]]></Location>
<VirtualAccess>$virtualacc</VirtualAccess>
<SessionType><![CDATA[$typename]]></SessionType>
<Virtualink><![CDATA[$meetinglink]]></Virtualink>
<SessionTags>

EOD;
			if (array_key_exists($session, $sessiontags)) {
				$tags = $sessiontags[$session];
				foreach ($tags as $tagname) {
					echo "<Tag><![CDATA[$tagname]]></Tag>\n";
				}
            }
			echo "</SessionTags>\n<Participants>\n";
			if (array_key_exists($session, $participantOnSession)) {
                $participants = $participantOnSession[$session];
                foreach ($participants  as $badgeid => $moderator) {
                    echo "<Participant moderator=\"$moderator\">$badgeid</Participant>\n";
                }
            }
			echo "</Participants>\n</Session>\n";
		}
		mysqli_free_result($result);
		echo "</Sessions>\n";
    }
	function retrieveD3XMLDataParticipants() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$results = array();
		if (prepare_db_and_more() === false) {
			$results["message_error"] = "Unable to connect to database.<br />No further execution possible.";
			return $results;
			};
		echo "<Participants>\n";
		// participant info
		$query = <<<EOD
SELECT DISTINCT
	P.badgeid, P.pubsname, IFNULL(P.htmlbio, P.bio) AS bio
FROM Participants P
JOIN ParticipantOnSession POS USING (badgeid)
JOIN Sessions S USING (sessionid)
JOIN Schedule SCH USING (sessionid)
WHERE S.pubstatusid = 2 /* Public */
ORDER BY P.pubsname
EOD;
		$result = mysqli_query_with_error_handling($query);

		while($row = mysqli_fetch_assoc($result)) {
			$pubsname = $row["pubsname"];
			$bio = $row["bio"];
			$badgeid = $row["badgeid"];
			echo <<<EOD
<Participant>
<Title><![CDATA[$pubsname]]></Title>
<Content><![CDATA[$bio]]></Content>
<ParticipantID>$badgeid</ParticipantID>
</Participant>

EOD;
		}
		mysqli_free_result($result);
		echo "</Participants>\n";
	}
	function retrieveD3XMLDataPocketProgram() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$results = array();
		if (prepare_db_and_more() === false) {
			$results["message_error"] = "Unable to connect to database.<br />No further execution possible.";
			return $results;
			};
		$ConStartDatim = CON_START_DATIM;

        // First get participant names and id's
		$query = <<<EOD
SELECT DISTINCT
	P.badgeid, P.pubsname
FROM Participants P
JOIN ParticipantOnSession POS USING (badgeid)
JOIN Sessions S USING (sessionid)
JOIN Schedule SCH USING (sessionid)
WHERE S.pubstatusid = 2 /* Public */
ORDER BY P.pubsname
EOD;
		$result = mysqli_query_with_error_handling($query);
		$participants = array();
		while($row = mysqli_fetch_assoc($result)) {
			$pubsname = $row["pubsname"];
			$badgeid = $row["badgeid"];
            //echo "p $badgeid: $pubsname\n";
			$participants[$badgeid] = $pubsname;
        }

		// first query: which people are on which sessions
		$query = <<<EOD
SELECT DISTINCT
	SCH.sessionid, POS.badgeid, POS.moderator
FROM Schedule SCH
JOIN Sessions S USING (sessionid)
JOIN ParticipantOnSession POS USING (sessionid)
WHERE
	S.pubstatusid = 2 /* Public */
ORDER BY
	SCH.sessionid,
	POS.moderator DESC,
	POS.badgeid;
EOD;
		$result = mysqli_query_with_error_handling($query);
		$participantOnSession = array();
		$cursessionid = -99999;
		$pos = "";
		while($row = mysqli_fetch_assoc($result)) {
			$sessionid = $row["sessionid"];
			$badgeid = $row["badgeid"];
			if ($sessionid != $cursessionid) {
                if ($cursessionid > 0) {
                    //echo "$cursessionid:" . mb_substr($pos, 0, mb_strlen($pos) - 2) . "\n";
                    $participantOnSession[$cursessionid] = mb_substr($pos, 0, mb_strlen($pos) - 2);
					$pos = "";
                }
                $cursessionid = $sessionid;
            }
            //echo "sp: $sessionid:$badgeid:" . $participants[$badgeid] . ":" . $row["moderator"] . "\n";
            $ppos = $participants[$badgeid] . ($row["moderator"] == "1" ? " (moderator)" : "");
            //echo "spl: $ppos\n";
			$pos .= "$ppos, ";
        }
		$participantOnSession[$cursessionid] = mb_substr($pos, 0, mb_strlen($pos) - 2);
        //echo "$cursessionid:$pos\n";
		mysqli_free_result($result);

		// second query -  session tags
		$query = <<<EOD
SELECT DISTINCT sessionid, tagname
FROM SessionHasTag s
JOIN Tags t USING (tagid)
ORDER BY sessionid, tagname;
EOD;
		$result = mysqli_query_with_error_handling($query);
		$sessiontags = array();
		$cursessionid = -99999;
		$tags = array();
		while($row = mysqli_fetch_assoc($result)) {
			$sessionid = $row["sessionid"];
			if ($sessionid != $cursessionid) {
                if ($cursessionid > 0) {
                    $sessiontags[$cursessionid] = $tags;
					$tags = array();
                }
                $cursessionid = $sessionid;
            }
			$tags[] = $row["tagname"];
        }
		$sessiontags[$cursessionid] = $tags;
		mysqli_free_result($result);

		// third query - Session data - output as XML
		$query = <<<EOD
SELECT
	s.sessionid, s.title, s.progguidhtml as description, s.meetinglink, s.duration,
	DATE_FORMAT(ADDTIME('$ConStartDatim',sch.starttime),'%Y-%m-%d %H:%i:%s') as date,
	r.roomname, t.typename
FROM Sessions s
JOIN Schedule sch USING (sessionid)
JOIN Rooms r USING (roomid)
JOIN Types t USING (typeid)
WHERE pubstatusid = 2
ORDER BY starttime, title, roomname;
EOD;
		$result = mysqli_query_with_error_handling($query);
		echo "<Sessions>\n";
		while($row = mysqli_fetch_assoc($result)) {
			$session = $row["sessionid"];
			$title = $row["title"];
			$description = $row["description"];
			$date = $row["date"];
			$roomname = $row["roomname"];
			$typename = $row["typename"];
			$meetinglink = $row["meetinglink"];
			echo <<<EOD
<Session>
<ID>$session</ID>
<Title>$title<Title>
<Content>$description</Content>
<Starttime>$date</Starttime>
<Location>$roomname</Location>
<SessionType>$typename</SessionType>
<Virtualink>$meetinglink</Virtualink>
<SessionTags>

EOD;
			if (array_key_exists($session, $sessiontags)) {
				$tags = $sessiontags[$session];
				foreach ($tags as $tagname) {
					echo "<Tag>$tagname</Tag>\n";
				}
            }
			echo "</SessionTags>\n";
			if (array_key_exists($session, $participantOnSession)) {
                $participants = $participantOnSession[$session];
				echo "<Participants>$participants</Participants>\n";
            }
			echo "</Session>\n";
		}
		mysqli_free_result($result);
		echo "</Sessions>\n";
    }
	function retrieveD3XMLDataAttendees() {
		$pgconn = pg_connect(WELLINGTONPROD);
		if (!$pgconn) {
			echo "Unable to connect to Wellington\n";
			exit();
		}

		// query: users with an attending/virtual membership and the earliest reservation/claim for that user
		$query = <<<EOD
SELECT membership_number, email,
CASE WHEN COALESCE(preferred_first_name, '')  = '' THEN first_name ELSE preferred_first_name END AS first_name,
CASE WHEN COALESCE (preferred_last_name, '')  = '' THEN last_name ELSE preferred_last_name END AS last_name
FROM (
	SELECT u.id, u.email, r.membership_number, m.name, dc.first_name, dc.last_name, dc.preferred_first_name, dc.preferred_last_name,
	ROW_NUMBER()  OVER (PARTITION BY u.id ORDER BY c.created_at) AS seq
	FROM users u
	JOIN claims c ON (c.user_id = u.id AND c.active_to IS NULL)
	JOIN reservations r ON (c.reservation_id = r.id)
	JOIN orders o ON (r.id = o.reservation_id AND o.active_to IS NULL)
	JOIN memberships m ON (o.membership_id = m.id)
	JOIN dc_contacts dc ON (dc.claim_id = c.id)
	WHERE (m.can_attend = true OR m.name LIKE '%virtual%')
	ORDER BY u.id, r.membership_number
	) s
where seq = 1
ORDER BY membership_number;
EOD;
		$result = pg_query($pgconn, $query);
		if (!$result) {
			echo "Wellington query error" . pg_result_error($result, PGSQL_STATUS_STRING) . "\n";
			exit();
		}
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo "<Attendees>\n";
		while ($row = pg_fetch_assoc($result)) {
			$id = $row["membership_number"];
			$email = $row["email"];
			$firstname = $row["first_name"];
			$lastname = $row["last_name"];
			echo <<<EOD
<Attendee>
<MembershipNumber>$id</MembershipNumber>
<Email><![CDATA[$email]]></Email>
<FirstName><![CDATA[$firstname]]></FirstName>
<LastName><![CDATA[$lastname]]></LastName>
</Attendee>

EOD;
        }
		pg_free_result($result);
		echo "</Attendees>\n";
    }
?>
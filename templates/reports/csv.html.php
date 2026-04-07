ID,Summary,State,Type,Priority,Queue,Version,Owners,Created,Assigned,Resolved
<?php foreach ($this->tickets as $ticket): ?>
<?php echo $ticket['id'] ?>,<?php echo $ticket['summary'] ?>,<?php echo $ticket['state_name'] ?>,<?php echo $ticket['type_name'] ?>,<?php echo $ticket['priority_name'] ?>,<?php echo $ticket['queue_name'] ?>,<?php echo $ticket['version_name'] ?>,<?php echo $ticket['owner_name'] ?>,<?php echo $ticket['date_created'] ?>,<?php echo $ticket['date_assigned'] ?? '' ?>,<?php echo $ticket['date_resolved'] ?? '' ?>
<?php endforeach ?>

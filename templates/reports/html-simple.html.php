<table>
<tr>
  <th>#</th>
  <th>Type</th>
  <th>Owners</th>
  <th>Open Date</th>
  <th>Description</th>
</tr>

<?php foreach ($this->tickets as $ticket): ?>
<tr>
  <td><a href="<?php echo $ticket['link'] ?>"><?php echo $ticket['id'] ?></a></td>
  <td><?php echo $ticket['type_name'] ?></td>
  <td><?php echo $ticket['owner_name'] ?></td>
  <td><?php echo $ticket['date_created'] ?></td>
  <td><?php echo $ticket['summary'] ?></td>
</tr>
<?php endforeach ?>

</table>

<?php
require_once 'functions.php';
require_login();
if(current_user()['role'] !== 'admin'){ flash_set('Access denied'); header('Location: index.php'); exit; }
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($mysqli, "DELETE FROM users WHERE id=$id");
    flash_set('User deleted');
    header('Location: users_manage.php'); exit;
}
$res = mysqli_query($mysqli, "SELECT * FROM users ORDER BY id DESC");
include 'header.php';
?>
<h4>Manage Users</h4>
<?php if($msg = flash_get()): ?><div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<table class="table table-bordered">
<tr><th>No</th><th>Username</th><th>Full Name</th><th>Role</th><th>Actions</th></tr>
<?php $i=1; while($row = mysqli_fetch_assoc($res)): ?>
<tr>
  <td><?php echo $i++; ?></td>
  <td><?php echo htmlspecialchars($row['username']); ?></td>
  <td><?php echo htmlspecialchars($row['full_name']); ?></td>
  <td><?php echo htmlspecialchars($row['role']); ?></td>
  <td>
    <a class="btn btn-sm btn-danger" href="users_manage.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete?')">Delete</a>
  </td>
</tr>
<?php endwhile; ?>
</table>
<?php include 'footer.php'; ?>
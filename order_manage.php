<?php
include 'connect.php';
include 'session_start.php';

$order_id = intval($_GET['order_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'])) {
        $item_id = intval($_POST['item_id'] ?? 0);
        $qty = max(1, intval($_POST['quantity'] ?? 1));

        $sql = "
            UPDATE order_items oi
            JOIN orders o ON oi.order_id = o.id
            SET oi.quantity = $qty
            WHERE oi.id = $item_id AND o.user_id = $user_id
        ";
        $conn->query($sql);

    } elseif (isset($_POST['cancel_order'])) {
        // 트랜잭션 시작
        $conn->begin_transaction();
        try {
            $conn->query("DELETE oi FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.id = $order_id AND o.user_id = $user_id");
            $conn->query("DELETE FROM orders WHERE id = $order_id AND user_id = $user_id");

            $conn->commit();
            echo "<script>alert('주문이 취소 되었습니다.'); location.href='mypage.php';</script>";
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("주문 취소 실패: " . $e->getMessage());
            echo "<script>alert('주문 취소 중 오류가 발생했습니다.');</script>";
        }
    }
}

// 주문 조회
$sql = "
    SELECT oi.id AS item_id, b.title, b.price, oi.quantity
    FROM order_items oi
    JOIN books b ON oi.book_id = b.id
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.order_id = $order_id AND o.user_id = $user_id
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주문 취소/변경 - 온라인 서점</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <main>
  <div class="container">
    <h2>주문 취소/변경</h2>
    <form method="post">
      <table class="cart-table">
        <thead>
          <tr><th>도서명</th><th>수량</th><th>금액</th><th>변경</th></tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <input type="number" name="quantity" value="<?= (int)$row['quantity'] ?>" min="1" />
              <input type="hidden" name="item_id" value="<?= (int)$row['item_id'] ?>">
            </td>
            <td><?= number_format($row['price'] * $row['quantity']) ?>원</td>
            <td>
              <button type="submit" name="update_qty" class="action-btn secondary-btn">수정</button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </form>

    <form method="post" style="margin-top: 20px;">
      <button type="submit" name="cancel_order" class="checkout-btn"
              onclick="return confirm('정말 주문을 취소하시겠습니까?');">
        주문 전체 취소
      </button>
    </form>
  </div>
</main>
<?php include 'footer.php' ?>
</body>
</html>

<?php
require 'connect.php';
require 'session_start.php';
require 'header.php';

// 사용자 입력값 검증 없이 직접 사용 (SQL Injection 가능!)
$user_id = $_SESSION['user_id'];

// 장바구니 조회 (SQL Injection)
$sql = "
    SELECT c.*, b.title, b.price
    FROM cart c
    JOIN books b ON c.book_id = b.id
    WHERE c.user_id = $user_id
";
$result = $conn->query($sql);

$total_price = 0;

// 보유 포인트 조회 (SQL Injection)
$sql = "SELECT point FROM users WHERE id = $user_id";
$result2 = $conn->query($sql);
$row2 = $result2->fetch_assoc();
$point = $row2['point'];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>주문 확인 - 취약한 KISIA_bookStore</title>
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="/css/cart.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  <div id="header-container"></div>

  <main>
    <div class="cart-container">
      <h2>주문 확인</h2>
      <table class="cart-table">
        <thead>
          <tr>
            <th style="width:50%;">상품정보</th>
            <th style="width:15%;">수량</th>
            <th style="width:15%;">금액</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()):
            // 사용자 입력값 검증 없이 출력 (XSS)
            $item_total = $row['price'] * $row['quantity'];
            $total_price += $item_total;
          ?>
          <tr>
            <td class="cart-product-info"><?= $row['title'] ?></td>
            <td><?= $row['quantity'] ?></td>
            <td><?= $item_total ?>원</td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <div class="cart-summary">
        <span>총 결제 금액: <strong id="total-price"><?= $total_price ?></strong>원</span><br>
        <span>보유 포인트: <strong id="user-point"><?= $point ?></strong>P</span><br>
        <span>잔여 포인트: <strong id="remaining-point"></strong>P</span><br>
        <span id="point-status" style="font-weight:bold;"></span>
      </div>

      <form action="order_process.php" method="post" class="order-form">
        <h3>배송 정보 입력</h3>

        <!-- 수령인 -->
        <div class="form-group">
          <label for="recipient">수령인</label>
          <input type="text" id="recipient" name="recipient">
        </div>

        <!-- 전화번호 -->
        <div class="form-group">
          <label>휴대폰</label>
          <div style="display:flex; gap:5px;">
            <input type="text" name="phone1" maxlength="3">
            <input type="text" name="phone2" maxlength="4">
            <input type="text" name="phone3" maxlength="4">
          </div>
        </div>

        <!-- 주소 -->
        <div class="form-group">
          <label>배송주소</label>
          <div style="display:flex; gap:8px;">
            <input type="text" id="postcode" name="postcode" placeholder="우편번호">
            <button type="button" onclick="execDaumPostcode()">주소 찾기</button>
          </div>
          <input type="text" id="roadAddress" name="road_address" placeholder="도로명 주소">
          <input type="text" id="jibunAddress" name="jibun_address" placeholder="지번 주소">
          <input type="text" id="detailAddress" name="detail_address" placeholder="상세 주소">
        </div>

        <!-- ✅ 여기에 추가: 클라이언트가 조작할 수 있는 금액/포인트 필드 -->
        <input type="hidden" name="total_price" id="total-price-input" value="">
        <input type="hidden" name="used_point" id="used-point-input" value="">

        <button type="submit" id="checkout-btn" class="checkout-btn">주문 확정</button>
      </form>
    </div>
  </main>
  <?php include 'footer.php'; ?>
</body>
</html>

<!-- 카카오 주소 API 스크립트 -->
<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
  function execDaumPostcode() {
    new daum.Postcode({
      oncomplete: function(data) {
        document.getElementById('postcode').value = data.zonecode;
        document.getElementById('roadAddress').value = data.roadAddress;
        document.getElementById('jibunAddress').value = data.jibunAddress;
      }
    }).open();
  }
</script>

<!-- ⚠️ 취약한 포인트 계산 및 상태 표시 (서버 검증 없이!) -->
<script>
  const totalPrice = parseInt(document.getElementById('total-price').innerText);
  const userPoint = parseInt(document.getElementById('user-point').innerText);

  const remainingPoint = userPoint - totalPrice;
  document.getElementById('remaining-point').innerText = remainingPoint;

  const statusElement = document.getElementById('point-status');
  if (remainingPoint >= 0) {
    statusElement.innerText = '결제 가능';
    statusElement.style.color = 'green';
  } else {
    statusElement.innerText = '포인트 부족';
    statusElement.style.color = 'red';
  }

  // ⚠️ 실제 결제 검증은 서버에서 이뤄져야 하지만, 여기는 일부러 취약하게 남김!
</script>

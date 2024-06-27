<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
	unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
	unset($_SESSION["csv_data_form"]);

require ('common.php');
try {
	$db = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]);

	if ($ClientUser['role'] == 1) {
		$tag_array = [];
		$stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=? ");
		$stmt->execute([$client_id]);
		$tags = $stmt->fetchAll();
		foreach ($tags as $t) {
			$tag_array[] = $t['tag_name'];
		}
		$tag_array[] = '未選択';
	} else {
		$tag_array = explode(',', $ClientUser['tags']);
	}

	if (isset($_GET['tag']) && $_GET['tag'] != '') {
		$tag = $_GET['tag'];
		$tag_ids = [];
		if ($tag !== '未選択') {
			$sql = "SELECT * FROM tags WHERE table_name = 'templates' AND tags LIKE '%$tag%'";
			$stmt = $db->prepare($sql);
			$stmt->execute();
			$result = $stmt->fetchAll();
			foreach ($result as $row) {
				$tag_ids[] = $row['table_id'];
			}
		}
	}
	// Modified query to include condition based on tag_ids
	$sql = "SELECT * FROM templates WHERE cid=?";

	if (!empty($tag_ids)) {
		$placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
		$sql .= " AND id IN ($placeholders)";
	}

	$sql .= " ORDER BY updated_at DESC";

	$stmt = $db->prepare($sql);
	$params = [$client_id];
	if (!empty($tag_ids)) {
		$params = array_merge($params, $tag_ids);
	}
	$stmt->execute($params);
	$template_data = $stmt->fetchAll();
	if (isset($tag) && empty($tag_ids) && $tag !== '未選択' && $tag !== '') {
		$template_data = [];
	}


	$stmt = $db->prepare("SELECT * FROM froms WHERE cid=?");
	$stmt->execute([$client_id]);
	$from_data = $stmt->fetchAll();

	$current = count($template_data);
	$max = $ClientData['max_temp'];
	$limit = $max - $current;

} catch (PDOException $e) {
	echo '接続失敗' . $e->getMessage();
	exit();
}
?>

<?php require ('header.php'); ?>
<style>
	.modal-body {
		height: auto;
		/* 適当な高さを指定 */
		overflow-y: auto;
		/* モーダルが内容に合わせてスクロールできるようにする */
	}

	@media (min-width: 576px) {
		.modal-dialog {
			max-width: 700px;
			margin: 1.75rem auto;
		}
	}

	menu {
		height: 100vh;
	}

	.dataTables_length {
		display: none;
	}

	.dataTables_filter {
		display: none;
	}

	.dataTables_paginate {
		float: left !important;
	}

	table.dataTable thead .sorting_desc {
		background-image: url(./img/sort_desc.png) !important;
	}

	table.dataTable thead .sorting_asc {
		background-image: url(./img/sort_asc.png) !important;
	}
</style>

<body>

	<div class="wrapper">
		<?php require ('menu.php'); ?>
		<main>
			<div class="d-flex justify-content-between">
				<div class="header-with-help">
					<h1>テンプレート設定</h1>
				</div>
				<?php require ('dropdown.php'); ?>
			</div>
			<table>
				<td>
					<?php require ('./common/restrict_modal.php'); ?>

					<input type="hidden" id="type" value="<?= $ClientData['max_temp_type'] ?>">
					<input type="hidden" id="max_value" value="<?= $ClientData['max_temp'] ?>">
					<input type="hidden" id="used_value" value="<?= count($template_data) ?>">
					<input type="hidden" id="limit" value="<?= $limit ?>">

					<?php if ($ClientData['max_cms_type'] == 1 && $limit <= 0): ?>
						<?php require_once ('./limitModal.php'); ?>
						<button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
							data-bs-target="#limitModal">新規登録</button>
					<?php else: ?>
						<button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
							data-bs-target="#new">新規登録</button>
					<?php endif; ?>
				</td>
				<td class="ml-2">
					<form action="" method="get">
						<label for="search_tag" class="ms-3">タグ検索</label>
						<select name="tag" id="">
							<option value="">全選択</option>
							<?php foreach ($tag_array as $each): ?>
								<option value="<?= $each; ?>" <?= (isset($tag) && $tag == $each) ? 'selected' : '' ?>>
									<?= $each; ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button class="btn btn-success">検索</button>
					</form>
				</td>
				<td>
					<div class="modal fade" id="insert_code_modal" data-bs-backdrop="static" data-bs-keyboard="false"
						tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
						<div class="modal-dialog modal-dialog-centered" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title">差し込みコード</h5>
									<button class="btn btn-secondary code-title-btn" id="code_default"
										onclick="code_default()">基本項目</button>
									<button class="btn btn-primary code-title-btn" id="code_pdf"
										onclick="code_pdf()">PDFファイル</button>
									<button class="btn btn-primary code-title-btn" id="code_redirect"
										onclick="code_redirect()">リダイレクト</button>
									<button class="btn btn-primary code-title-btn" id="code_cms"
										onclick="code_cms()">CMS</button>
									<button type="button" class="btn-close" data-bs-dismiss="modal"
										aria-label="Close"></button>
								</div>
								<?php
								require ('./template_insert_code/pdf.php');
								require ('./template_insert_code/redl.php');
								require ('./template_insert_code/code.php');
								require ('./template_insert_code/cms.php');
								?>
								<div class="modal-footer">
									<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
										data-bs-toggle="modal" id="back_button">戻る</button>
								</div>
							</div>
						</div>
					</div>
				</td>
				<td>
					<?php
					if ($ClientData['max_temp_type'] == 1) {
						echo "<span>上限数：" . $limit . "</span>";
					}
					?>
				</td>
			</table>
			<br>
			<br>

			<table class="table" id="temp_table">
				<thead>
					<tr>
						<th>No.</th>
						<th>区分</th>
						<!-- <th>差出アドレス</th> -->
						<th>テンプレート名</th>
						<th>更新日時</th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($template_data as $key => $row): ?>
						<?php
						try {
							$db = new PDO($dsn, $user, $pass, [
								PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
							]);
							$stmt = $db->prepare("SELECT * FROM froms WHERE id=?");
							$stmt->execute([$row['from_id']]);
							$from_data_ = $stmt->fetch(PDO::FETCH_ASSOC);

							$stmt = $db->prepare("SELECT * FROM tags WHERE table_name = 'templates' AND table_id=?");
							$stmt->execute([$row['id']]);
							$tag_data_ = $stmt->fetch(PDO::FETCH_ASSOC);

							if (!isset($tag_data_['tags'])) {
								$temp2tag = '未選択';
							} else {
								$temp2tag = $tag_data_['tags'];
							}

							if (isset($_GET['tag']) && $_GET['tag'] == '未選択') {
								if ($temp2tag != '未選択') {
									continue;
								}
							} else {
								$check_tag = explode(',', $temp2tag);
								$commonValues = array_intersect($check_tag, $tag_array);
								if (empty($commonValues)) {
									continue;
								}
							}

						} catch (PDOException $e) {
							echo '接続失敗' . $e->getMessage();
							exit();
						}
						?>
						<tr>
							<td>
								<?= $key + 1 ?>
							</td>
							<td>
								<?= $row['division'] ?>
							</td>
							<!-- <td>
								<?= $row['division'] === 'メール配信' && isset($from_data_['email']) ? trim($from_data_['email']) : '' ?>
							</td> -->
							<td>
								<?= $row['subject'] ?>
							</td>
							<td>
								<?= substr($row['updated_at'], 0, 16) ?>
							</td>
							<td>
								<?php if ($row['division'] == 'フォーム営業'): ?>
									<a href="./send_list.php?title=<?= $row['subject']; ?>" class="btn btn-secondary">一覧</a>
								<?php elseif ($row['division'] == 'メール配信'): ?>
									<a href="./distribution_list.php?search=<?= $row['division'] === 'メール配信' && isset($from_data_['email']) ? trim($from_data_['email']) : '' ?>&subject=<?= $row['subject'] ?>"
										class="btn btn-secondary">一覧</a>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="btn btn-primary" data-bs-toggle="modal"
									data-bs-target="#detail"
									onClick="handleClick(`<?= isset($from_data_['email']) ? $from_data_['id'] : ''; ?>`,`<?= $row['subject']; ?>`,`<?= $row['content']; ?>`,`<?= $row['id']; ?>`,`<?= $row['from_id']; ?>`,`<?= $row['division']; ?>`,`<?= isset($from_data_['email']) ? $row['from_id'] : ''; ?>`,`<?= $temp2tag; ?>`);">
									詳細
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</main>
	</div>
	<?php
	$tag_array = array_diff($tag_array, ["未選択"]);
	?>
	<div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
		aria-labelledby="staticBackdropLabel" aria-hidden="true">

		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">新規登録</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close">
						<!-- <span aria-hidden="true">&times;</span> -->
					</button>
				</div>
				<!-- modal-body -->
				<div class="modal-body" id="modalgit _req_new">
					<form action="./back/new_template.php" id="new_form" method="post">
						<table>
							<tr>
								<td class="p-1">区分</td>
								<td class="p-1"><span class="require-note">必須</span>
									<select name="division" id="new_division" onchange="DivisionNew(this.value)">
										<option value="メール配信">メール配信</option>
										<option value="フォーム営業">フォーム営業</option>
										<option value="SNS">SNS</option>
									</select>
								</td>
							</tr>
							<tr>
								<td class="p-1">テンプレート名</td>
								<td class="p-1"><span class="require-note">必須</span>
									<input type="text" name="subject" id="new_subject" style="width:80%">
								</td>
								<script>
									const templateData = <?= json_encode($template_data) ?>;
									const subjectInput = document.querySelector(
										'input[name="subject"]');
									const submitButton = document.querySelector(
										'button[type="submit"]');

									subjectInput.addEventListener('blur', () => {
										const subject = subjectInput.value;
										const existingTemplate = templateData.find(template =>
											template.subject === subject);

										if (existingTemplate) {
											alert('同じテンプレート名のテンプレートが既に存在します。別のテンプレート名を入力してください。');
											subjectInput.value = '';
											submitButton.disabled = true;
										} else {
											submitButton.disabled = false;
										}
									});
								</script>
							</tr>
							<tr>
								<td class="p-1">本文</td>
								<td class="p-1" class="space-1">
									<textarea name="content" id="new_content" cols="60" rows="10"></textarea>
								</td>
							</tr>
							<tr class="mb-2">
								<td class="p-1">タグ</td>
								<td class="p-1">
									<?php $i = 0;
									foreach ($tag_array as $each):
										$i++; ?>
										<input type="checkbox" name="select_tags[<?= $i; ?>]" value="<?= $each; ?>" />
										<label for="">
											<?= $each; ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</table>
				</div>
				<div class="modal-footer">
					<input type="hidden" name="cid" value="<?= $client_id ?>">
					<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-dismiss="modal"
						data-bs-target="#insert_code_modal" id="code_copy">差し込みコード</button>
					<button type="button" onclick="newSave()" class="btn btn-secondary">新規登録</button>
					</form>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
						id="cancel_button">閉じる</button>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
		aria-labelledby="staticBackdropLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" id="modal_content">
				<div class="modal-header">
					<h5 class="modal-title">差出元</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
						onclick="cancelEditing()"></button>
				</div>
				<!-- modal-body -->
				<div class="modal-body" id="modal_req">
					<form action="./back/ch_template.php" id="detail_form" method="post">
						<table class="table">
							<tr>
								<td>区分</td>
								<td class="space-2">
									<span id="division"></span>
									<input type="hidden" name="division" id="division1">
								</td>
							</tr>
							<tr id="email_box" class="d-none">
								<td>差出アドレス</td>
								<td>
									<!-- <span id="email"></span> -->
									<span class="require-note">必須</span>
									<!-- <input type="hidden" name="email" id="email1"> -->
									<select name="email" id="email" disabled>
										<option value="" disabled>未選択</option>
										<?php foreach ($from_data as $from): ?>
											<option value="<?= $from['id'] ?>">
												<?= $from['email'] ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td>テンプレート名</td>
								<td>
									<span class="require-note">必須</span>
									<input type="text" name="subject" id="subject" style="width:80%" disabled>
								</td>
							</tr>
							<tr>
								<td>本文</td>
								<td class="space-2">
									<textarea name="content" id="content" cols="60" rows="10" disabled></textarea>
									<p><span id="content_number"></span>文字</p>
								</td>
							</tr>
							<tr>
								<td>タグ</td>
								<td id="tagContainer" class="d-none">
									<?php $i = 0;
									foreach ($tag_array as $each):
										$i++; ?>
										<input type="checkbox" name="detail_tags[<?= $i; ?>]" value="<?= $each; ?>"
											disabled />
										<label for="">
											<?= $each; ?>
										</label>
									<?php endforeach; ?>
								</td>
								<td id="currentTags"></td>
							</tr>
						</table>
						<br>
						<br>
				</div>
				<div class="modal-footer">
					<input type="hidden" name="template_id" id="template_id">
					<!-- <input type="hidden" name="from_id" id="from_id"> -->
					<input type="hidden" name="cid" value="<?= $client_id; ?>">
					<div class="w-100 d-flex justify-content-between">
						<div class="w-25">
							<button type="button" class="btn btn-danger" onclick="del()" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
						</div>
						<div class="d-flex justify-content-end">
							<button type="button" class="btn btn-primary me-2" data-bs-toggle="modal"
								data-bs-dismiss="modal" data-bs-target="#insert_code_modal"
								id="code_copy">差し込みコード</button>
							<button type="button" id="edit_button" class="btn btn-dark" onclick="enableEditing()"
								<?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
							<button type="button" id="save_button" onclick="detailSave()" class="btn btn-success"
								style="display:none">保存</button>
							</form>
							<button type="button" class="btn btn-secondary ms-2" onclick="cancelEditing()"
								data-bs-dismiss="modal">閉じる</button>
						</div>
					</div>
					<form action="./back/del_temp.php" id="del_form" method="post">
						<input type="hidden" name="id" id="delete_id" value="">
					</form>
				</div>
			</div>
		</div>
	</div>
	<script src="./assets/js/client/restrict.js"></script>
	<script src="./assets/js/client/template_setting.js"></script>
	<script>
		let inputs = document.querySelectorAll('#modal_req input, #modal_req textarea, #modal_req select');
		let editButton = document.getElementById('edit_button');
		let saveButton = document.getElementById('save_button');
		let closeBtn = document.getElementById('close');
		let division_value = "";

		function enableEditing() {
			inputs.forEach(input => {
				input.disabled = false;
			});
			editButton.style.display = 'none';
			saveButton.style.display = 'inline-block';
			$('#tagContainer').removeClass('d-none');
			$('#currentTags').addClass('d-none');
		}

		closeBtn.addEventListener('click', function () {
			inputs.forEach(input => {
				input.disabled = true;
			});
			saveButton.style.display = "none";
			editButton.style.display = "block";
		});

		function cancelEditing() {
			inputs.forEach(input => {
				input.disabled = true;
			});
			saveButton.style.display = "none";
			editButton.style.display = "block";
			$('#tagContainer').addClass('d-none');
			$('#currentTags').removeClass('d-none');
		}

		function Division(val) {
			if (val == "フォーム営業" || val == "SNS") {
				document.getElementById("email_box").style.display = "none";
				// document.getElementById("email").disabled = "true";
			} else {
				document.getElementById("email_box").style.display = "contents";
				// document.getElementById("email").disabled = "false";
			}
		}

		function DivisionNew(val) {
			if (val == "フォーム営業" || val == "SNS") {
				document.getElementById("email-box").style.display = "none";
				// document.getElementById("new_email_title").style.display = "none";
				// document.getElementById("new_email_select").style.display = "none";
				// document.getElementById("new_email").style.display = "none";
			} else {
				document.getElementById("email-box").style.display = "contents";
				// document.getElementById("new_email_title").style.display = "block";
				// document.getElementById("new_email_select").style.display = "block";
				// document.getElementById("new_email").style.display = "block";
			}
		}
		function countLetters(inputString) {
			var stringWithoutBraces = inputString.replace(/{.*?}/g, '');
			var numberOfLetters = stringWithoutBraces.replace(/[^a-zA-Zぁ-んァ-ン一-龠]/g, '').length;

			return numberOfLetters;
		}
		function handleClick(email, subject, content, template_id, from_id, division, from_id, tags) {
			document.getElementById("email").value = email;
			// document.getElementById("email1").value = from_id;
			document.getElementById("subject").value = subject;
			document.getElementById("content").value = content;
			document.getElementById("content_number").innerText = countLetters(content);
			document.getElementById("template_id").value = template_id;
			document.getElementById("delete_id").value = template_id;
			document.getElementById("division").innerHTML = division;
			document.getElementById("division1").value = division;

			document.getElementById("currentTags").innerHTML = tags;
			var tagsArray = tags.split(',');

			var checkboxes = document.querySelectorAll('input[name^="detail_tags"]');

			checkboxes.forEach(function (checkbox) {
				if (tagsArray.indexOf(checkbox.value) !== -1) {
					checkbox.checked = true;
				} else {
					checkbox.checked = false;
				}
			});

			division_value = division;
			// document.getElementById("from_id").value = from_id;

			if (division == "フォーム営業" || division == "SNS") {
				// document.getElementById("division").options[1].selected = true;

				document.getElementById("email_box").style.display = "none";

			} else if (division == "メール配信") {
				// document.getElementById("division").options[0].selected = true;

				document.getElementById("email_box").style.display = "contents";

			}

			let back_button = document.getElementById('back_button');
			back_button.setAttribute('data-bs-target', '#detail');

		}



		function handleSelect(division) {
			const selectElement = document.getElementById('division');
			const editButton = document.getElementById('edit_button');
			const saveButton = document.getElementById('save_button');

			if (editButton.style.display === 'none' && saveButton.style.display === 'inline-block') {
				selectElement.disabled = false;
			} else {
				selectElement.disabled = true;
			}
		}

		function handleClickInsertCode(template_data) {
			let insert_code_list = template_data.split(',');
			for (var i = 0; i < insert_code_list.length; i++) {
				document.getElementById("insert_code_" + i).value = insert_code_list[i];
			}
		}

		function del() {
			var select = confirm("データを削除してもよろしいですか？");
			let form = document.getElementById('del_form');
			if (select) {
				form.submit();
			} else {
				return;
			}
		}

		function addText(text) {
			var area = document.getElementById('content');
			area.value = area.value.substr(0, area.selectionStart) +
				text +
				area.value.substr(area.selectionStart);

			return false
		}

		function detailSave() {
			var form = document.getElementById("detail_form");

			var email = document.getElementById("email").value;
			var subject = document.getElementById("subject").value;
			if (subject != "") {
				form.submit();
			} else {
				alert("テンプレート名を入力してください。")
				document.getElementById('subject').focus();
			}
		}

		async function copyToClipboard(obj) {
			const element = obj.previousElementSibling;
			if (navigator.clipboard && window.isSecureContext) {
				await navigator.clipboard.writeText(element.value);
				copy_alert();
			} else {
				const textarea = document.createElement('textarea');
				textarea.value = element.value;
				textarea.style.position = 'absolute';
				textarea.style.left = '-99999999px';
				document.body.prepend(textarea);
				textarea.focus();
				textarea.select();
				try {
					document.execCommand('copy');
					copy_alert();
				} catch (err) {
					alert('コピーに失敗しました。')
				} finally {
					textarea.remove();
				}
			}
		}
		function copy_alert() {
			$(document).ready(function () {
				toastr.options.timeOut = 1500; // 1.5s
				toastr.success('コピーしました。');
			});
		}
	</script>
	<script>
		$(document).ready(function () {
			$('#temp_table').DataTable({
				"language": {
					"url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
				},
				"dom": '<"top"p>rt<"bottom"i><"clear">',
				"order": [
					[3, 'desc']
				]
			});
		});
	</script>
	<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
		integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
		crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
		integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
		crossorigin="anonymous"></script>
</body>

</html>
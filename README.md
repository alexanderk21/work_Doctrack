参考サイト
https://bonz-net.com/xmpp-imagemagic-install-php7_4-725/

#で囲んである箇所は当方のバージョンなので適宜読み替えてください

【phpinfo()でバージョンの確認】
一つ目のテーブル内
##########
PHP Version 8.1.2
Architecture x64
PHP Extension Build API......, TS,VS16
##########

【imageMagickダウンロード】
https://windows.php.net/downloads/pecl/deps/
ImageMagick-7.1.0-18-vc15-x64.zipをダウンロード

【imagickダウンロード】
https://pecl.php.net/package/imagick
State列がstableの最新バージョンの
Download列のDLLを確認
PHPのバージョンが合っているものをダウンロード
##########
8.1 Thread Safe (TS) x64
##########

【imageMagickの展開】
xampp配下にimagemagickフォルダ作成
ImageMagick-7.1.0-18-vc15-x64.zipを展開
展開したファイル内のbinフォルダの中身を全てxampp/imagemagick内にコピー

【iamgickの展開】
php_imagick.dllを
xampp/php/extフォルダにコピー

【php.ini編集】
xampp/php/php.iniを編集
extension=php_imagick.dllを追記
extension=php_fileinfo.dllがコメントアウトもしくはなければ追記

【パスを通す】
環境変数>下側のシステム環境変数>Pathを選択
「編集」クリック
「新規」->「参照」をクリック
ImageMagickのbinフォルダをコピーしたフォルダ（xampp/imagemagick）を選択
「OK」

【確認】
PCを再起動
XAMPP起動
http://127.0.0.1/dashboard/phpinfo.phpにアクセス
アルファベット順にモジュールが並んでいるので確認

【実行】
htdocs
  ┗ sample
      ┣ convert.php
      ┗ testPDF.pdf <-変換したいPDFファイル


http://127.0.0.1/sample/convert.phpにアクセス
htdocs/sample/配下にconverted.jpgが生成される


【エラー】
エラーが出た場合、

こちらを参考にエラーログの出力
https://office-obata.com/report/memorandum/post-4864

Ghostscriptが無いというエラーが出た場合、以下を参考にインストール
https://blog.nyanco.me/ghostscript-introduction-to-windows/
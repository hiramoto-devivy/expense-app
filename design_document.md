# 小規模会社向け経費精算アプリケーション 設計書（フルスタック TypeScript/Node.js版）

Vue 3 + TypeScript を使用して、モダンで使いやすい小規模な会社向けの経費精算アプリケーションを作成します。バックエンドはサードパーティの依存を減らすため、Node.js の標準ライブラリ (`node:http`, `node:sqlite`) のみで構築し、ビルド後はサーバーに配置してすぐに実行できるようにします。

## 1. プロジェクト構成

フロントエンドとバックエンドを1つのリポジトリ内で管理します。

- `backend/`: Node.js (標準の `http` モジュールと `node:sqlite` を使用)
- `frontend/`: Vue 3, Vite, TypeScript

## 2. バックエンド（Node.js 標準ライブラリ + node:sqlite）

Express等の外部フレームワークは使用せず、最新Node.jsの標準機能で完結させます。

### データベース設計 (SQLite)

以下のテーブルを作成します。

- **Users**: ユーザー認証用（`id`, `username`, `password`）※パスワードのハッシュ化は行わず、指示通り `password` とします。
- **Categories**: 経費カテゴリ（`id`, `name`）。初期データとして「交通費」「交際費」「消耗品」「出張費」を投入。
- **Expenses**: 経費データ（`id`, `user_id`, `category_id`, `amount`, `date`, `year_month` (年月管理用), `description`, `receipt_file_path` (領収書ファイルパス)）

### APIエンドポイント

- `POST /api/auth/login`: ログイン処理（JWTトークン発行。有効期限は30分に設定。トークン生成には標準の `crypto` 等を利用するかシンプルに実装）
- `GET /api/auth/verify`: トークン検証用（画面更新時のログイン状態復元に使用）
- `GET /api/categories`: カテゴリ一覧取得
- `GET /api/expenses`: ユーザーの経費一覧取得
- `POST /api/expenses`: 経費追加（ファイルアップロード対応：領収書画像/PDFをBase64形式等で送信し、サーバー内の `uploads/` ディレクトリに保存）
- `PUT /api/expenses/:id`: 経費更新
- `DELETE /api/expenses/:id`: 経費削除
- `GET /api/uploads/:filename`: アップロードされた領収書ファイルの取得・表示用エンドポイント

## 3. フロントエンド（Vue 3 + Vite + TypeScript）

### 認証とルーティング（Vue Router）

- **ナビゲーションガード**: 未認証の場合は `/login` にリダイレクトし、認証済みの場合はダッシュボード（`/`）に遷移。
- `src/views/Login.vue` [NEW]: ログイン画面
- `src/views/Dashboard.vue` [NEW]: ダッシュボード（年月やカテゴリでの表示）
- `src/views/ExpenseList.vue` [NEW]: 経費一覧
- `src/views/AddExpense.vue` [NEW]: 経費追加フォーム

### 状態管理とAPI通信

- **Pinia**: 認証状態と経費データ、カテゴリデータを管理。
- **ログイン状態の永続化と判定（画面更新時の対応）**:
  - 取得したJWTトークンはブラウザの `localStorage` に保存します。これにより、**画面を更新（F5等）してもログイン状態が保持**されます。
  - アプリケーション起動時（または画面更新時）に、Pinia初期化処理で `localStorage` のトークンを読み込み、バックエンド（`/api/auth/verify` など）で有効性を検証してから画面を描画するアプローチを想定しています。
  - また、トークンが期限切れ等で無効な場合、APIリクエスト時に `401 Unauthorized` が返るため、Axios/Fetchの共通処理（インターセプター等の仕組み）で自動的にローカルの情報を破棄し、ログイン画面へ遷移させます。
- **Native Fetch API**: Axiosは使用せず、ブラウザ標準の `fetch` を使用してセキュアにAPI通信を行います。リクエストヘッダーにJWTトークンを付与します。

### UIデザイン・スタイリング
>
> [!TIP]
> プレーンなCSS（Vanilla CSS）を使用し、洗練されたモダンなデザイン（滑らかなグラデーション、グラスモーフィズム、マイクロアニメーション）を実装します。

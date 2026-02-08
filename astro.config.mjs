import { defineConfig } from 'astro/config';
import tailwind from '@astrojs/tailwind';
import sitemap from '@astrojs/sitemap'; // サイトマップ用に追加

// https://astro.build/config
export default defineConfig({
  // ① 自分のURLをここに追記（末尾のスラッシュは無しでOK）
  site: 'https://sbt-inc.jp', 
  
  integrations: [
    tailwind(), 
    // ② サイトマップの統合設定を追加
    sitemap()
  ],
});
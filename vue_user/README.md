# Vue User Frontend

用户前端项目 - 基于 Vue 3 + Vite + Element Plus + Tailwind CSS

## 功能特性

- ✅ 用户登录/注册/退出
- ✅ 动态菜单/权限控制
- ✅ 主题切换 (浅色/深色)
- ✅ 多语言支持 (中文/英文)
- ✅ PC/手机自适应布局
- ✅ 拖拽页面动态渲染
- ✅ 个人中心管理
- ✅ 密码修改

## 技术栈

- Vue 3 (Composition API)
- Vite 5
- Vue Router 4
- Pinia
- Vue I18n
- Element Plus
- Tailwind CSS
- Axios

## 安装

```bash
npm install
```

## 开发

```bash
npm run dev
```

## 构建

```bash
npm run build
```

## 预览

```bash
npm run preview
```

## 目录结构

```
vue_user/
├── src/
│   ├── api/              # API 接口
│   ├── components/       # 公共组件
│   ├── directive/        # 自定义指令
│   ├── lang/             # 多语言
│   ├── layouts/          # 布局组件
│   ├── router/           # 路由配置
│   ├── store/            # Pinia Store
│   ├── styles/           # 样式文件
│   ├── utils/            # 工具函数
│   ├── views/            # 页面组件
│   ├── App.vue           # 根组件
│   └── main.js           # 入口文件
├── index.html
├── package.json
├── vite.config.js
├── tailwind.config.js
└── postcss.config.js
```

## API 配置

项目默认代理到 `http://localhost:8000`，可在 `vite.config.js` 中修改。

## 打包 APP

1. 执行 `npm run build` 生成 dist 目录
2. 使用 HBuilderX 导入 dist 目录
3. 云打包生成 APK/IPA

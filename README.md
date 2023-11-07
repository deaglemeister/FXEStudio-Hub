# FXEdition 17 | Сборка для DevelNext 16.7.0
<p align="center">
  <img alt="FXEdition White" src="https://github.com/tjmcraft/TJMC-Launcher/assets/68428951/6b8227a8-2e0e-475a-bc2a-aee59eaf0080" width="400">
  <img alt="FXEdition Black" src="https://github.com/tjmcraft/TJMC-Launcher/assets/68428951/7944f6c3-0be1-489f-8424-394495b14046" width="400">
</p>

## About project ✨
[![Build status](https://github.com/tjmcraft/TJMC-Launcher/actions/workflows/electron.yml/badge.svg?branch=main)](https://github.com/tjmcraft/TJMC-Launcher/actions/workflows/electron.yml)
[![GitHub release](https://img.shields.io/github/release/tjmcraft/TJMC-Launcher.svg)](https://github.com/tjmcraft/TJMC-Launcher/releases/latest)
[![CodeFactor](https://www.codefactor.io/repository/github/tjmcraft/TJMC-Launcher/badge)](https://www.codefactor.io/repository/github/tjmcraft/TJMC-Launcher)
[![dev chat](https://discordapp.com/api/guilds/693099755269783643/widget.png?style=shield)](https://discord.gg/PpHb5gfR)

This project was originally created by [MakAndJo](https://github.com/MakAndJo) and then moved to [TJMC-Company](https://github.com/tjmcraft) (for *non-commercial* purpose only). \
**TJMC-Launcher** is a simple to use, extremely fast, and well supported app, that allows you to install **pure** and **modded** versions of **Java Minecraft**. \
**Current status:** [MVP+](https://ru.wikipedia.org/wiki/%D0%9C%D0%B8%D0%BD%D0%B8%D0%BC%D0%B0%D0%BB%D1%8C%D0%BD%D0%BE_%D0%B6%D0%B8%D0%B7%D0%BD%D0%B5%D1%81%D0%BF%D0%BE%D1%81%D0%BE%D0%B1%D0%BD%D1%8B%D0%B9_%D0%BF%D1%80%D0%BE%D0%B4%D1%83%D0%BA%D1%82)

## Download 💿
If you are looking to install **TJMC-Launcher** without setting up a development environment, you can consume our binary [releases](https://github.com/tjmcraft/TJMC-Launcher/releases).

| Windows 8.1+ Setup | MacOS 10.15+ dmg | MacOS 10.15+ zip | Linux deb | Linux tar |
| :---: | :---: | :---: | :---: | :---: |
| [x64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-x64.exe) | [x64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-x64.dmg) \| [arm64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-arm64.dmg) | [x64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-x64.zip) \| [arm64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-arm64.zip) | [x64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-amd64.deb) \| [arm64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-arm64.deb) | [x64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-x64.tar.gz) \| [arm64](https://github.com/tjmcraft/TJMC-Launcher/releases/latest/download/TJMC-Launcher-setup-arm64.tar.gz) |

#### Warning ⚠️
If you have any troubles with Linux Snap package **auth**, run this command in bash shell:
```sh
sudo snap connect tjmc-launcher:password-manager-service
```

## Developing 💻

### Downloading the source code:

Clone the repository with `git`:

```sh
git clone https://github.com/tjmcraft/TJMC-Launcher
cd TJMC-Launcher
```

To update the source code to the latest commit, run the following command inside the `TJMC-Launcher` directory:

```sh
git fetch
git pull
```

### Available Scripts:
`npm run start` - Runs the app in the normal mode \
`npm run build` - Build electron app with default config \
`npm run build:win` - Build electron app for windows only \
`npm run build:mac` - Build electron app for darwin only \
`npm run build:linux` - Build electron app for linux only \
`npm run build:linux-snap` - Build electron app for linux for snap store \
`npm run serve-render:dev` - Serve UI on dedicated server \
`npm run watch-render:dev` - Start watching UI in dev mode for dist build \
`npm run build-render:dev` - Build UI in dev mode for dist build \
`npm run build-render:prod` - Build UI in production mode for dist build \
`npm run deploy` - Build and publish electron app with default config \
`npm run deploy:win` - Build and publish electron app only for windows \
`npm run deploy:mac` - Build and publish electron app only for darwin \
`npm run deploy:linux` - Build and publish electron app only for linux \
`npm run deploy:linux-snap` - Build and publish electron app for linux and publish to snap store \
`npm run deploy:multi` - Build and publish electron app for all available platforms

## Libraries 📦

### Used libraries for **Host**:
 - [**Electron**](https://github.com/electron/electron)
 - [electron-builder](https://github.com/electron-userland/electron-builder)
 - [adm-zip](https://github.com/cthackers/adm-zip)
 - [fs-extra](https://github.com/jprichardson/node-fs-extra)
 - [got](https://github.com/sindresorhus/got)
 - [md5](https://github.com/pvorb/node-md5)
 - [ws](https://github.com/websockets/ws)

 ### Used libraries for **UI**:
 - [**Babel**](https://github.com/babel/babel)
 - [**Preact**](https://github.com/preactjs/preact)
 - [**Webpack**](https://github.com/webpack/webpack)
 - [babel-loader](https://github.com/babel/babel-loader)
 - [copy-webpack-plugin](https://github.com/webpack-contrib/copy-webpack-plugin)
 - [css-loader](https://github.com/webpack-contrib/css-loader)
 - [html-webpack-loader](https://github.com/maskletter/html-webpack-loader)
 - [mini-css-extract-plugin](https://github.com/webpack-contrib/mini-css-extract-plugin)
 - [style-loader](https://github.com/webpack-contrib/style-loader)
 - [svg-inline-loader](https://github.com/webpack-contrib/svg-inline-loader)
 - [svg-url-loader](https://github.com/bhovhannes/svg-url-loader)
 - [terser-webpack-plugin](https://github.com/webpack-contrib/terser-webpack-plugin)
 - [url-loader](https://github.com/webpack-contrib/url-loader)
 - [react-markdown](https://github.com/remarkjs/react-markdown)
 - [remark-gfm](https://github.com/remarkjs/remark-gfm)
 - [platform](https://github.com/bestiejs/platform.js)

 ## License 📝
 All code are licensed under [MIT Licence](https://github.com/tjmcraft/TJMC-Launcher/blob/main/LICENSE)

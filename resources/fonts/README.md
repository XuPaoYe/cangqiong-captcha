# 验证码字体

当前字体为 **Foundation Captcha Sans Medium**：Noto Sans SC 的验证码专用子集，固定 500 字重，覆盖全部 44 个候选汉字及数字字母题型所用的 26 个 ASCII 字符。此字体由苍穹验证码选择并生成子集。保持字框居中与随机旋转规则，未修改答案坐标或校验容差。

- 来源：[Google Fonts / Noto Sans SC](https://github.com/google/fonts/tree/bd8f81ddb5c74d5c8897b36ad88b440266245103/ofl/notosanssc)。
- 原始文件：`NotoSansSC[wght].ttf`，SHA256 `a3041811a78c361b1de50f953c805e0244951c21c5bd412f7232ef0d899af0da`。
- 许可：完整 SIL OFL 1.1，见 NotoSansSC-OFL.txt；免费商用。原字体保留名称为 Source，派生子集已更名，保留原版权信息，不单独销售字体。
- 子集工具：fonttools 4.60.1；仅素材维护时需要，生产和浏览器均不依赖 Python 或 Google Fonts 在线加载。

复现：在独立 Python 虚拟环境安装 `fonttools==4.60.1`，下载上述固定提交的原始字体，然后在仓库内执行 `python resources/fonts/subset.py /绝对路径/NotoSansSC-variable.ttf`。脚本验证原始 SHA256，再从 PHP 候选字池生成子集；改变字池后必须重新生成、检查可见字形、更新 resources/manifest.json 并运行 `composer resources:check`。OFL 取同一固定来源提交，禁止换源后复用旧校验值。

历史字体 ZCOOL XiaoWei 已移出交付包；它是 PHP 移植的自选字体，不是官方内置字体。

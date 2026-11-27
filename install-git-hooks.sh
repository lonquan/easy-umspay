#!/bin/bash
#
# Git Hooks 安装脚本
# 将项目的 Git hooks 安装到 .git/hooks 目录
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOOKS_DIR="$SCRIPT_DIR/.git/hooks"

echo "📦 安装 Git hooks..."
echo ""

# 确保 hooks 目录存在
mkdir -p "$HOOKS_DIR"

# 创建 pre-commit hook
cat > "$HOOKS_DIR/pre-commit" << 'EOF'
#!/bin/sh
#
# Git pre-commit hook
# 在提交前运行 composer check 确保代码质量
#

echo "🔍 运行 pre-commit 检查..."
echo ""

# 运行 composer check
composer check

# 获取 composer check 的退出状态码
CHECK_EXIT_CODE=$?

# 如果检查失败，阻止提交
if [ $CHECK_EXIT_CODE -ne 0 ]; then
    echo ""
    echo "❌ composer check 未通过，提交被阻止！"
    echo ""
    echo "请修复上述问题后再次提交："
    echo "  composer fix        - 自动修复代码风格问题"
    echo "  composer check      - 手动运行所有检查"
    echo ""
    exit 1
fi

echo ""
echo "✅ 所有检查通过！"
echo ""

exit 0
EOF

# 添加执行权限
chmod +x "$HOOKS_DIR/pre-commit"

echo "✅ Git hooks 安装成功！"
echo ""
echo "已安装的 hooks："
echo "  - pre-commit: 在提交前运行 composer check"
echo ""
echo "提示："
echo "  - 使用 'composer fix' 自动修复代码风格问题"
echo "  - 使用 'composer check' 手动运行所有检查"
echo ""


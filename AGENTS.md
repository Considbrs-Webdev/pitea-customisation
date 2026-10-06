# AGENTS.md

Guidance for coding agents (Codex, Cursor, Claude Code) working in this repository. For a description of the codebase, architecture and commands, read `CLAUDE.md`.

## Agent skills

Project skills live in **`.agents/skills/<skill-name>/SKILL.md`**. That is the single source of truth. Codex only reads `.agents/skills/`, and Cursor reads it too, so do not put the real file anywhere else.

Claude Code reads `.claude/skills/`, so every skill is exposed there as a **relative symlink** to the real folder:

```bash
ln -s ../../.agents/skills/<skill-name> .claude/skills/<skill-name>
```

Do not add a symlink in `.cursor/skills/`. Cursor already reads `.agents/skills/` and `.claude/skills/`, so a third copy would make the skill appear more than once. Never copy a skill into more than one directory.

### Creating a skill

1. Create `.agents/skills/<skill-name>/SKILL.md`. The folder name is lowercase letters, numbers and hyphens, and must equal `name` in the frontmatter (Cursor requires this).
2. Start the file with YAML frontmatter:

   ```markdown
   ---
   name: <skill-name>
   description: >-
     What the skill does and when to use it. Say when it should and should not trigger.
   ---
   ```

   `name` and `description` are required in all three tools. Put everything else (steps, commands, rules) in the body. Do not rely on tool-specific frontmatter fields.
3. Add the symlink for Claude Code (see above) and check that `ls -la .claude/skills` shows it pointing at `../../.agents/skills/<skill-name>`.
4. Write skills so they work in any of the three tools: use plain shell commands and repo-relative paths, and do not refer to a specific tool's features.
5. Commit `.agents/skills/` and `.claude/skills/`. Do not commit `.claude/settings.local.json`, which is personal.

Existing skills: `release` (merge `dev` into `main`, bump version, changelog, tag, GitHub release).

### Commits and releases

Do not add attribution lines such as "Generated with Claude Code" or `Co-Authored-By` to commit messages or pull request or release descriptions.

import { useState, useEffect, useCallback } from "react";

// ─── CONFIG ───────────────────────────────────────────────────────────────────
// In production (Vercel), set VITE_GITHUB_TOKEN, VITE_GITHUB_OWNER,
// and VITE_GITHUB_REPOS (comma-separated) as environment variables.
// In dev, create a .env.local file with those same keys.
// If env vars are present, the setup panel hides automatically.
// Token needs: public_repo (REST) + read:project (GraphQL Projects v2).

const ENV_TOKEN = import.meta.env.VITE_GITHUB_TOKEN || "";
const ENV_OWNER = import.meta.env.VITE_GITHUB_OWNER || "";
const ENV_REPOS = import.meta.env.VITE_GITHUB_REPOS || "";

const REFRESH_INTERVAL_MS = 60_000;

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function relTime(dateStr) {
  if (!dateStr) return "";
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.round(diff / 60000);
  if (mins < 2) return "just now";
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.round(hrs / 24)}d ago`;
}

function labelColor(name = "") {
  const n = name.toLowerCase();
  if (n.includes("agent") || n.includes("vera") || n.includes("ai")) return "#7F77DD";
  if (n.includes("bug")) return "#E24B4A";
  if (n.includes("infra") || n.includes("deploy")) return "#1D9E75";
  return "#888780";
}

function progressColor(pct) {
  if (pct >= 80) return "#1D9E75";
  if (pct >= 40) return "#BA7517";
  return "#E24B4A";
}

// ─── DATA FETCHING ────────────────────────────────────────────────────────────
async function fetchRepo(owner, repo, token) {
  const h = { Authorization: `token ${token}`, Accept: "application/vnd.github.v3+json" };
  const [repoRes, issuesRes, prsRes, milestonesRes, commitsRes] = await Promise.all([
    fetch(`https://api.github.com/repos/${owner}/${repo}`, { headers: h }),
    fetch(`https://api.github.com/repos/${owner}/${repo}/issues?state=open&per_page=50`, { headers: h }),
    fetch(`https://api.github.com/repos/${owner}/${repo}/pulls?state=open&per_page=20`, { headers: h }),
    fetch(`https://api.github.com/repos/${owner}/${repo}/milestones?state=open`, { headers: h }),
    fetch(`https://api.github.com/repos/${owner}/${repo}/commits?per_page=10`, { headers: h }),
  ]);
  const [repoData, issues, prs, milestones, commits] = await Promise.all([
    repoRes.ok ? repoRes.json() : {},
    issuesRes.ok ? issuesRes.json() : [],
    prsRes.ok ? prsRes.json() : [],
    milestonesRes.ok ? milestonesRes.json() : [],
    commitsRes.ok ? commitsRes.json() : [],
  ]);
  return {
    repo, repoData,
    issues: (Array.isArray(issues) ? issues : []).filter(i => !i.pull_request),
    prs: Array.isArray(prs) ? prs : [],
    milestones: Array.isArray(milestones) ? milestones : [],
    commits: Array.isArray(commits) ? commits : [],
  };
}

async function fetchProjects(owner, token) {
  const query = `
    query($org: String!) {
      organization(login: $org) {
        projectsV2(first: 10) {
          nodes {
            title
            url
            fields(first: 20) {
              nodes {
                ... on ProjectV2SingleSelectField {
                  name
                  options { name id }
                }
              }
            }
            items(first: 100) {
              nodes {
                fieldValues(first: 10) {
                  nodes {
                    ... on ProjectV2ItemFieldSingleSelectValue {
                      name
                      field {
                        ... on ProjectV2SingleSelectField { name }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }`;
  const res = await fetch("https://api.github.com/graphql", {
    method: "POST",
    headers: {
      Authorization: `bearer ${token}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ query, variables: { org: owner } }),
  });
  if (!res.ok) return { boards: [], error: `GraphQL ${res.status}` };
  const json = await res.json();
  if (json.errors?.length) {
    return { boards: [], error: json.errors[0].message };
  }
  return { boards: json.data?.organization?.projectsV2?.nodes || [], error: null };
}

function summarizeBoard(project) {
  const statusField = project.fields?.nodes?.find(
    f => f && f.name && /status/i.test(f.name)
  );
  const optionNames = statusField?.options?.map(o => o.name) || [];
  const counts = Object.fromEntries(optionNames.map(n => [n, 0]));
  let unassigned = 0;
  const items = project.items?.nodes || [];
  for (const item of items) {
    const statusVal = item.fieldValues?.nodes?.find(
      v => v && v.name && v.field && /status/i.test(v.field.name)
    );
    if (statusVal && counts[statusVal.name] !== undefined) {
      counts[statusVal.name]++;
    } else {
      unassigned++;
    }
  }
  return {
    title: project.title,
    url: project.url,
    columns: optionNames.map(n => ({ name: n, count: counts[n] })),
    unassigned,
    totalItems: items.length,
  };
}

// ─── COMPONENTS ──────────────────────────────────────────────────────────────

function MetricCard({ label, value }) {
  return (
    <div style={{ background: "#f5f5f3", borderRadius: 8, padding: "14px 16px" }}>
      <div style={{ fontSize: 12, color: "#888780", marginBottom: 6 }}>{label}</div>
      <div style={{ fontSize: 28, fontWeight: 500 }}>{value}</div>
    </div>
  );
}

function MilestoneBar({ milestone }) {
  const total = (milestone.open_issues || 0) + (milestone.closed_issues || 0);
  const pct = total ? Math.round((milestone.closed_issues / total) * 100) : 0;
  return (
    <div style={{ marginBottom: 10 }}>
      <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, marginBottom: 4 }}>
        <span style={{ fontWeight: 500 }}>{milestone.title}</span>
        <span style={{ color: "#888" }}>{pct}%</span>
      </div>
      <div style={{ height: 4, background: "#eee", borderRadius: 2, overflow: "hidden" }}>
        <div style={{ height: "100%", width: `${pct}%`, background: progressColor(pct), borderRadius: 2 }} />
      </div>
      {milestone.due_on && (
        <div style={{ fontSize: 11, color: "#aaa", marginTop: 3 }}>
          due {new Date(milestone.due_on).toLocaleDateString()}
        </div>
      )}
    </div>
  );
}

function RepoCard({ data }) {
  return (
    <div style={{ background: "#fff", border: "0.5px solid #e0dfd8", borderRadius: 12, padding: "1rem 1.25rem" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
        <span style={{ fontWeight: 500 }}>📦 {data.repo}</span>
        <span style={{ fontSize: 11, color: "#aaa", background: "#f5f5f3", padding: "2px 8px", borderRadius: 99 }}>
          {data.repoData?.private ? "private" : "public"}
        </span>
      </div>
      <div style={{ display: "flex", gap: 16, marginBottom: 12 }}>
        {[["issues", data.issues.length], ["PRs", data.prs.length], ["milestones", data.milestones.length]].map(([k, v]) => (
          <span key={k} style={{ fontSize: 13, color: "#666" }}>
            <strong style={{ color: "#222" }}>{v}</strong> {k}
          </span>
        ))}
      </div>
      {data.milestones.length > 0 && (
        <div style={{ borderTop: "0.5px solid #eee", paddingTop: 10 }}>
          {data.milestones.slice(0, 3).map(m => <MilestoneBar key={m.id} milestone={m} />)}
        </div>
      )}
      {data.commits[0] && (
        <div style={{ borderTop: "0.5px solid #eee", paddingTop: 8, fontSize: 12, color: "#aaa", marginTop: 8 }}>
          last commit: {data.commits[0].commit?.message?.split("\n")[0]?.slice(0, 60)} · {relTime(data.commits[0].commit?.author?.date)}
        </div>
      )}
    </div>
  );
}

function IssueRow({ issue, repo }) {
  return (
    <div style={{ display: "flex", alignItems: "flex-start", gap: 10, padding: "10px 0", borderBottom: "0.5px solid #eee" }}>
      <div style={{ width: 8, height: 8, borderRadius: "50%", background: "#1D9E75", marginTop: 5, flexShrink: 0 }} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 500, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
          <a href={issue.html_url} target="_blank" rel="noreferrer" style={{ color: "inherit", textDecoration: "none" }}>
            {repo} #{issue.number} — {issue.title}
          </a>
        </div>
        <div style={{ fontSize: 11, color: "#aaa", margin: "3px 0" }}>
          {relTime(issue.updated_at)}
          {issue.assignees?.length > 0 && " · " + issue.assignees.map(a => a.login).join(", ")}
        </div>
        {issue.labels?.length > 0 && (
          <div style={{ display: "flex", gap: 5, flexWrap: "wrap", marginTop: 4 }}>
            {issue.labels.map(l => (
              <span key={l.id} style={{ fontSize: 11, padding: "2px 8px", borderRadius: 99,
                background: `${labelColor(l.name)}22`, color: labelColor(l.name) }}>
                {l.name}
              </span>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function ActivityItem({ event }) {
  const icons = { issue: "🔵", pr: "🟢", push: "⚪" };
  return (
    <div style={{ display: "flex", gap: 10, padding: "8px 0", borderBottom: "0.5px solid #eee" }}>
      <span style={{ fontSize: 12, marginTop: 2 }}>{icons[event.type] || "⚪"}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
          <strong>{event.repo}</strong> — {event.title}
        </div>
        <div style={{ fontSize: 11, color: "#aaa", marginTop: 2 }}>
          {relTime(event.date)}{event.author ? " · " + event.author : ""}
        </div>
      </div>
    </div>
  );
}

function BoardCard({ board }) {
  const hasColumns = board.columns.length > 0;
  return (
    <div style={{ background: "#fff", border: "0.5px solid #e0dfd8", borderRadius: 12, padding: "1rem 1.25rem", marginBottom: 12 }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
        <span style={{ fontWeight: 500 }}>
          {board.url ? (
            <a href={board.url} target="_blank" rel="noreferrer" style={{ color: "inherit", textDecoration: "none" }}>
              📋 {board.title}
            </a>
          ) : <>📋 {board.title}</>}
        </span>
        <span style={{ fontSize: 11, color: "#aaa" }}>{board.totalItems} items</span>
      </div>
      {hasColumns ? (
        <div style={{ display: "grid", gridTemplateColumns: `repeat(${board.columns.length}, minmax(80px, 1fr))`, gap: 8 }}>
          {board.columns.map(col => (
            <div key={col.name} style={{ background: "#f5f5f3", borderRadius: 8, padding: "10px 12px", textAlign: "center" }}>
              <div style={{ fontSize: 11, color: "#888780", marginBottom: 4, textTransform: "lowercase" }}>{col.name}</div>
              <div style={{ fontSize: 22, fontWeight: 500 }}>{col.count}</div>
            </div>
          ))}
        </div>
      ) : (
        <div style={{ fontSize: 12, color: "#aaa" }}>no Status field configured on this project</div>
      )}
      {board.unassigned > 0 && (
        <div style={{ fontSize: 11, color: "#aaa", marginTop: 8 }}>
          {board.unassigned} item{board.unassigned === 1 ? "" : "s"} without status
        </div>
      )}
    </div>
  );
}

// ─── MAIN APP ─────────────────────────────────────────────────────────────────
export default function App() {
  const [token, setToken] = useState(ENV_TOKEN);
  const [owner, setOwner] = useState(ENV_OWNER);
  const [reposStr, setReposStr] = useState(ENV_REPOS);
  const [results, setResults] = useState([]);
  const [boards, setBoards] = useState([]);
  const [boardError, setBoardError] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [lastUpdated, setLastUpdated] = useState(null);
  const [activeTab, setActiveTab] = useState("overview");
  const [labelFilter, setLabelFilter] = useState("all");
  const [showSetup, setShowSetup] = useState(!ENV_TOKEN);

  const load = useCallback(async (t = token, o = owner, r = reposStr) => {
    if (!t || !o || !r) return;
    setLoading(true);
    setError("");
    try {
      const repos = r.split(",").map(s => s.trim()).filter(Boolean);
      const [data, projectsResult] = await Promise.all([
        Promise.all(repos.map(repo => fetchRepo(o, repo, t))),
        fetchProjects(o, t),
      ]);
      setResults(data);
      setBoards(projectsResult.boards.map(summarizeBoard));
      setBoardError(projectsResult.error || "");
      setLastUpdated(new Date());
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [token, owner, reposStr]);

  // Auto-load if env vars present
  useEffect(() => { if (ENV_TOKEN && ENV_OWNER && ENV_REPOS) load(); }, []);

  // Auto-refresh every 60s
  useEffect(() => {
    if (!results.length) return;
    const id = setInterval(() => load(), REFRESH_INTERVAL_MS);
    return () => clearInterval(id);
  }, [results, load]);

  const totalIssues = results.reduce((a, r) => a + r.issues.length, 0);
  const totalPRs = results.reduce((a, r) => a + r.prs.length, 0);
  const totalMilestones = results.reduce((a, r) => a + r.milestones.length, 0);

  const allIssues = results.flatMap(r => r.issues.map(i => ({ ...i, _repo: r.repo })));
  const filteredIssues = labelFilter === "all"
    ? allIssues
    : allIssues.filter(i => i.labels?.some(l => l.name.toLowerCase().includes(labelFilter)));

  const activity = results.flatMap(r => [
    ...r.issues.map(i => ({ type: "issue", repo: r.repo, title: `#${i.number} ${i.title}`, date: i.created_at })),
    ...r.prs.map(p => ({ type: "pr", repo: r.repo, title: `#${p.number} ${p.title}`, date: p.created_at })),
    ...r.commits.map(c => ({ type: "push", repo: r.repo, title: c.commit?.message?.split("\n")[0], date: c.commit?.author?.date, author: c.commit?.author?.name })),
  ]).sort((a, b) => new Date(b.date) - new Date(a.date));

  const Tab = ({ id, label }) => (
    <button onClick={() => setActiveTab(id)} style={{
      fontSize: 13, padding: "8px 16px", border: "none", background: "transparent",
      color: activeTab === id ? "#222" : "#888", fontWeight: activeTab === id ? 500 : 400,
      borderBottom: activeTab === id ? "2px solid #222" : "2px solid transparent",
      cursor: "pointer", marginBottom: -1
    }}>{label}</button>
  );

  return (
    <div style={{ maxWidth: 900, margin: "0 auto", padding: "1.5rem", fontFamily: "system-ui, sans-serif", color: "#222" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1.5rem" }}>
        <div>
          <h1 style={{ fontSize: 20, fontWeight: 500, margin: 0 }}>project dashboard</h1>
          <div style={{ fontSize: 12, color: "#aaa", marginTop: 2 }}>
            {lastUpdated ? `updated ${lastUpdated.toLocaleTimeString()}` : "not loaded"}
          </div>
        </div>
        <div style={{ display: "flex", gap: 8 }}>
          <button onClick={() => setShowSetup(s => !s)} style={{ fontSize: 12, padding: "5px 12px", border: "0.5px solid #ddd", borderRadius: 8, background: "transparent", cursor: "pointer" }}>
            ⚙ config
          </button>
          <button onClick={() => load()} disabled={loading} style={{ fontSize: 12, padding: "5px 12px", border: "0.5px solid #ddd", borderRadius: 8, background: "transparent", cursor: "pointer" }}>
            {loading ? "loading…" : "↻ refresh"}
          </button>
        </div>
      </div>

      {showSetup && (
        <div style={{ background: "#fff", border: "0.5px solid #e0dfd8", borderRadius: 12, padding: "1.25rem", marginBottom: "1.5rem" }}>
          <div style={{ fontSize: 14, fontWeight: 500, marginBottom: 12 }}>connect to github</div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 10 }}>
            <div>
              <label style={{ fontSize: 12, color: "#666", display: "block", marginBottom: 4 }}>token (public_repo + read:project)</label>
              <input type="password" value={token} onChange={e => setToken(e.target.value)} placeholder="ghp_..."
                style={{ width: "100%", padding: "6px 10px", border: "0.5px solid #ccc", borderRadius: 8, fontSize: 13 }} />
            </div>
            <div>
              <label style={{ fontSize: 12, color: "#666", display: "block", marginBottom: 4 }}>owner</label>
              <input type="text" value={owner} onChange={e => setOwner(e.target.value)} placeholder="your-github-org"
                style={{ width: "100%", padding: "6px 10px", border: "0.5px solid #ccc", borderRadius: 8, fontSize: 13 }} />
            </div>
          </div>
          <div style={{ marginBottom: 12 }}>
            <label style={{ fontSize: 12, color: "#666", display: "block", marginBottom: 4 }}>repos (comma-separated)</label>
            <input type="text" value={reposStr} onChange={e => setReposStr(e.target.value)} placeholder="vera-os, cat-iq, pulse-report"
              style={{ width: "100%", padding: "6px 10px", border: "0.5px solid #ccc", borderRadius: 8, fontSize: 13 }} />
          </div>
          <button onClick={() => { load(); setShowSetup(false); }} style={{ fontSize: 13, padding: "6px 14px", border: "0.5px solid #ccc", borderRadius: 8, background: "transparent", cursor: "pointer" }}>
            load dashboard
          </button>
        </div>
      )}

      {error && (
        <div style={{ background: "#FCEBEB", color: "#A32D2D", borderRadius: 8, padding: "10px 14px", fontSize: 13, marginBottom: "1rem" }}>
          {error}
        </div>
      )}

      {results.length > 0 && (
        <>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 10, marginBottom: "1.5rem" }}>
            <MetricCard label="repos tracked" value={results.length} />
            <MetricCard label="open issues" value={totalIssues} />
            <MetricCard label="open pull requests" value={totalPRs} />
            <MetricCard label="active milestones" value={totalMilestones} />
          </div>

          <div style={{ borderBottom: "0.5px solid #eee", marginBottom: "1.25rem", display: "flex" }}>
            <Tab id="overview" label="overview" />
            <Tab id="issues" label={`issues (${totalIssues})`} />
            <Tab id="activity" label="activity" />
            <Tab id="board" label={`board${boards.length ? ` (${boards.length})` : ""}`} />
          </div>

          {activeTab === "overview" && (
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))", gap: 12 }}>
              {results.map(r => <RepoCard key={r.repo} data={r} />)}
            </div>
          )}

          {activeTab === "issues" && (
            <>
              <div style={{ display: "flex", gap: 8, marginBottom: "1rem", flexWrap: "wrap" }}>
                {["all", "agent", "bug", "infra"].map(f => (
                  <button key={f} onClick={() => setLabelFilter(f)} style={{
                    fontSize: 12, padding: "4px 12px", borderRadius: 99,
                    border: "0.5px solid #ccc", background: labelFilter === f ? "#f5f5f3" : "transparent",
                    fontWeight: labelFilter === f ? 500 : 400, cursor: "pointer"
                  }}>{f}</button>
                ))}
              </div>
              {filteredIssues.length
                ? filteredIssues.slice(0, 50).map(i => <IssueRow key={`${i._repo}-${i.id}`} issue={i} repo={i._repo} />)
                : <div style={{ color: "#aaa", fontSize: 13, padding: "2rem 0" }}>no issues match this filter</div>}
            </>
          )}

          {activeTab === "activity" && (
            <div>{activity.slice(0, 40).map((e, i) => <ActivityItem key={i} event={e} />)}</div>
          )}

          {activeTab === "board" && (
            <div>
              {boardError && (
                <div style={{ background: "#FFF8E5", color: "#7A5A00", borderRadius: 8, padding: "10px 14px", fontSize: 12, marginBottom: "1rem" }}>
                  board unavailable: {boardError}. token needs <code>read:project</code> scope.
                </div>
              )}
              {boards.length > 0
                ? boards.map((b, i) => <BoardCard key={i} board={b} />)
                : !boardError && (
                  <div style={{ color: "#aaa", fontSize: 13, padding: "2rem 0", textAlign: "center" }}>
                    no Projects v2 boards found for this org
                  </div>
                )}
            </div>
          )}
        </>
      )}

      {!results.length && !loading && (
        <div style={{ textAlign: "center", padding: "3rem 0", color: "#aaa", fontSize: 13 }}>
          enter config above and click load dashboard
        </div>
      )}
    </div>
  );
}

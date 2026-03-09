import { format } from 'date-fns'
import clsx from 'clsx'
import type { LogEntry } from '../../types'

interface LogTableProps {
  logs: LogEntry[]
  loading?: boolean
  onRowClick?: (log: LogEntry) => void
}

function getStatusColor(statusCode: number): string {
  if (statusCode >= 200 && statusCode < 300) return 'bg-status-success/10 text-status-success'
  if (statusCode >= 400 && statusCode < 500) return 'bg-status-warning/10 text-status-warning'
  if (statusCode >= 500) return 'bg-status-danger/10 text-status-danger'
  return 'bg-surface-tertiary text-text-primary'
}

function getMethodColor(method: string): string {
  const colors: Record<string, string> = {
    GET: 'text-status-info',
    POST: 'text-status-success',
    PUT: 'text-status-warning',
    PATCH: 'text-orange-600',
    DELETE: 'text-status-danger',
  }
  return colors[method] || 'text-text-secondary'
}

function formatResponseTime(ms: number): string {
  if (ms < 1000) return `${Math.round(ms)}ms`
  return `${(ms / 1000).toFixed(2)}s`
}

export default function LogTable({
  logs,
  loading = false,
  onRowClick,
}: LogTableProps) {
  if (loading) {
    return (
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-border-subtle">
          <thead className="bg-surface-secondary">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Timestamp
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Type
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Endpoint
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Status
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Response Time
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                User
              </th>
            </tr>
          </thead>
          <tbody className="bg-surface divide-y divide-border-subtle">
            {[...Array(5)].map((_, i) => (
              <tr key={i}>
                <td className="px-6 py-4">
                  <div className="h-4 bg-surface-tertiary rounded animate-pulse w-32" />
                </td>
                <td className="px-6 py-4">
                  <div className="h-4 bg-surface-tertiary rounded animate-pulse w-16" />
                </td>
                <td className="px-6 py-4">
                  <div className="h-4 bg-surface-tertiary rounded animate-pulse w-48" />
                </td>
                <td className="px-6 py-4">
                  <div className="h-4 bg-surface-tertiary rounded animate-pulse w-12" />
                </td>
                <td className="px-6 py-4">
                  <div className="h-4 bg-surface-tertiary rounded animate-pulse w-16" />
                </td>
                <td className="px-6 py-4">
                  <div className="h-4 bg-surface-tertiary rounded animate-pulse w-20" />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  if (logs.length === 0) {
    return (
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-border-subtle">
          <thead className="bg-surface-secondary">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Timestamp
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Type
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Endpoint
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Status
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                Response Time
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
                User
              </th>
            </tr>
          </thead>
          <tbody className="bg-surface divide-y divide-border-subtle">
            <tr>
              <td colSpan={6} className="px-6 py-8 text-center text-text-muted">
                No logs found. Try adjusting your filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    )
  }

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-border-subtle">
        <thead className="bg-surface-secondary">
          <tr>
            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
              Timestamp
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
              Type
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
              Endpoint
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
              Status
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
              Response Time
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">
              User
            </th>
          </tr>
        </thead>
        <tbody className="bg-surface divide-y divide-border-subtle">
          {logs.map((log) => (
            <tr
              key={log.id}
              onClick={() => onRowClick?.(log)}
              className={clsx(
                'transition-colors',
                onRowClick && 'cursor-pointer hover:bg-surface-secondary'
              )}
            >
              <td className="px-6 py-4 whitespace-nowrap text-sm text-text-muted">
                {format(new Date(log.timestamp), 'MMM d, HH:mm:ss')}
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-sm">
                {log.is_outbound ? (
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800" title="Outbound HTTP request to external service">
                    ↗ OUT
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-status-info/10 text-status-info" title="Inbound HTTP request to your API">
                    ↙ IN
                  </span>
                )}
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-sm">
                <div className="flex items-center gap-1">
                  <span className={clsx('font-medium', getMethodColor(log.method))}>
                    {log.method}
                  </span>
                  <span className="text-text-primary">{log.endpoint}</span>
                  {log.third_party_service && (
                    <span className="ml-2 text-xs text-text-muted">
                      ({log.third_party_service})
                    </span>
                  )}
                </div>
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                <span
                  className={clsx(
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    getStatusColor(log.status_code)
                  )}
                >
                  {log.status_code}
                </span>
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-text-muted">
                {formatResponseTime(log.response_time_ms)}
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-text-muted">
                {log.user_name || log.user_id || '-'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

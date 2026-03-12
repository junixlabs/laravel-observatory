import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { AlertTriangle, CheckCircle, RefreshCw } from 'lucide-react'
import { useHealthAlerts } from '@/hooks/useHealthAlerts'
import { useQueryClient } from '@tanstack/react-query'

interface StatusBarProps {
  sidebarCollapsed: boolean
}

export default function StatusBar({ sidebarCollapsed }: StatusBarProps) {
  const { critical, warnings, lastUpdated, isLoading } = useHealthAlerts()
  const queryClient = useQueryClient()
  const [timeSinceUpdate, setTimeSinceUpdate] = useState('just now')

  // Update time display
  useEffect(() => {
    const updateTimeDisplay = () => {
      const now = new Date()
      const diff = Math.floor((now.getTime() - lastUpdated.getTime()) / 1000)

      if (diff < 5) {
        setTimeSinceUpdate('just now')
      } else if (diff < 60) {
        setTimeSinceUpdate(`${diff}s ago`)
      } else if (diff < 3600) {
        setTimeSinceUpdate(`${Math.floor(diff / 60)}m ago`)
      } else {
        setTimeSinceUpdate(`${Math.floor(diff / 3600)}h ago`)
      }
    }

    updateTimeDisplay()
    const interval = setInterval(updateTimeDisplay, 1000)
    return () => clearInterval(interval)
  }, [lastUpdated])

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['stats'] })
    queryClient.invalidateQueries({ queryKey: ['inboundStats'] })
    queryClient.invalidateQueries({ queryKey: ['outboundStats'] })
    queryClient.invalidateQueries({ queryKey: ['jobStats'] })
  }

  return (
    <footer
      className={cn(
        'fixed bottom-0 right-0 h-8 z-30',
        'flex items-center justify-between px-4',
        'border-t text-xs transition-all duration-300'
      )}
      style={{
        left: sidebarCollapsed ? '64px' : '240px',
        backgroundColor: 'var(--bg-primary)',
        borderColor: 'var(--border-primary)',
        color: 'var(--text-muted)',
      }}
    >
      {/* Left section - Status indicators */}
      <div className="flex items-center gap-4">
        {/* Critical alerts */}
        {critical > 0 && (
          <button
            className="flex items-center gap-1.5 px-2 py-0.5 rounded transition-colors"
            style={{
              backgroundColor: 'rgba(239, 68, 68, 0.1)',
              color: 'var(--status-error)',
            }}
          >
            <AlertTriangle className="w-3.5 h-3.5" />
            <span className="font-medium">{critical} Critical</span>
          </button>
        )}

        {/* Warnings */}
        {warnings > 0 && (
          <button
            className="flex items-center gap-1.5 px-2 py-0.5 rounded transition-colors"
            style={{
              backgroundColor: 'rgba(245, 158, 11, 0.1)',
              color: 'var(--status-warning)',
            }}
          >
            <AlertTriangle className="w-3.5 h-3.5" />
            <span className="font-medium">{warnings} Warning{warnings !== 1 ? 's' : ''}</span>
          </button>
        )}

        {/* All clear indicator */}
        {critical === 0 && warnings === 0 && !isLoading && (
          <div
            className="flex items-center gap-1.5 px-2 py-0.5 rounded"
            style={{
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              color: 'var(--status-success)',
            }}
          >
            <CheckCircle className="w-3.5 h-3.5" />
            <span className="font-medium">All Systems Operational</span>
          </div>
        )}
      </div>

      {/* Center section - Separator */}
      <div
        className="h-4 w-px mx-4"
        style={{ backgroundColor: 'var(--border-primary)' }}
      />

      {/* Right section - Last updated & version */}
      <div className="flex items-center gap-4">
        {/* Last updated with refresh */}
        <div className="flex items-center gap-2">
          <span>Last updated: {timeSinceUpdate}</span>
          <button
            onClick={handleRefresh}
            className="p-1 rounded transition-colors hover:bg-gray-500/10"
            title="Refresh data"
          >
            <RefreshCw className={cn('w-3.5 h-3.5', isLoading && 'animate-spin')} />
          </button>
        </div>

        {/* Separator */}
        <div
          className="h-4 w-px"
          style={{ backgroundColor: 'var(--border-primary)' }}
        />

        {/* Version */}
        <Link to="/whats-new" className="hover:underline">SidMonitor v0.3.0</Link>
      </div>
    </footer>
  )
}

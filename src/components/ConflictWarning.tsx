import React from 'react';
import { ConflictCheckResult, SmartSuggestion } from '../types/index';
import { AlertTriangle, CheckCircle2, Clock, MapPin, Sparkles, UserX, Calendar } from 'lucide-react';

interface ConflictWarningProps {
  conflictResult: ConflictCheckResult;
  onApplySuggestion: (suggestion: SmartSuggestion) => void;
}

export const ConflictWarning: React.FC<ConflictWarningProps> = ({
  conflictResult,
  onApplySuggestion
}) => {
  if (!conflictResult.hasConflict) {
    return (
      <div className="bg-emerald-50 border border-emerald-200 rounded-2xl p-3.5 text-emerald-900 text-xs flex items-center space-x-2.5">
        <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
        <div>
          <span className="font-bold text-emerald-900">No Conflicts Detected:</span>
          <span className="ml-1 text-emerald-800">
            Selected room, time interval, and participants are 100% available with zero schedule overlaps.
          </span>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs space-y-3">
      {/* Header */}
      <div className="flex items-start space-x-2.5 text-amber-900">
        <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="font-bold text-sm text-amber-900">
            Scheduling Conflicts Detected ({conflictResult.conflicts.length})
          </h4>
          <p className="text-amber-800 mt-0.5 font-medium">
            The UoH Conflict Engine found overlapping reservations or participant schedule constraints.
          </p>
        </div>
      </div>

      {/* Conflicts List */}
      <div className="space-y-2">
        {conflictResult.conflicts.map((conf, idx) => (
          <div key={idx} className="bg-white border border-amber-200 rounded-xl p-3 space-y-1 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="font-bold text-amber-900 flex items-center space-x-1.5">
                {conf.type === 'room' && <MapPin className="w-3.5 h-3.5 text-amber-600" />}
                {conf.type === 'participant' && <UserX className="w-3.5 h-3.5 text-amber-600" />}
                {conf.type === 'university_event' && <Calendar className="w-3.5 h-3.5 text-amber-600" />}
                <span>{conf.title}</span>
              </span>
              {conf.existingTime && (
                <span className="text-[10px] bg-amber-100 text-amber-900 border border-amber-300 px-2 py-0.5 rounded-lg font-mono font-bold">
                  {conf.existingTime}
                </span>
              )}
            </div>
            <p className="text-slate-600 text-[11px] leading-relaxed">{conf.description}</p>
          </div>
        ))}
      </div>

      {/* Smart Availability Engine Suggestions */}
      {conflictResult.suggestions.length > 0 && (
        <div className="mt-3 pt-3 border-t border-amber-200 space-y-2">
          <div className="flex items-center space-x-1.5 text-indigo-700 font-bold text-xs">
            <Sparkles className="w-4 h-4 text-indigo-600" />
            <span>Smart Availability Suggestions (1-Click Apply)</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {conflictResult.suggestions.map((sug, i) => (
              <div 
                key={i} 
                className="bg-white border border-indigo-200 rounded-xl p-3 flex flex-col justify-between space-y-2 shadow-xs"
              >
                <div>
                  <div className="font-bold text-slate-900 flex items-center justify-between">
                    <span>{sug.roomName}</span>
                    <span className="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-lg border border-indigo-100">
                      Cap: {sug.capacity}
                    </span>
                  </div>
                  <div className="text-[10px] text-slate-500 font-medium flex items-center space-x-1 mt-1">
                    <Clock className="w-3 h-3 text-indigo-600" />
                    <span>{sug.date} • {sug.startTime} - {sug.endTime}</span>
                  </div>
                  <p className="text-[10px] text-slate-600 mt-1 italic">{sug.reason}</p>
                </div>

                <button
                  type="button"
                  onClick={() => onApplySuggestion(sug)}
                  className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold py-1.5 rounded-xl shadow-xs transition-colors cursor-pointer flex items-center justify-center space-x-1"
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  <span>Apply This Slot</span>
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};


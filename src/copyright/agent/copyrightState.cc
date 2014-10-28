/*
 * Copyright (C) 2014, Siemens AG
 * Author: Johannes Najjar, Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "copyrightState.hpp"

CopyrightState::CopyrightState(int _agentId, int _verbosity) :
        agentId(_agentId),
        verbosity(_verbosity),
        regexMatchers()
{

}

CopyrightState::~CopyrightState()
{
}

int CopyrightState::getAgentId() const
{
  return agentId;
};

int CopyrightState::getVerbosity() const
{
  return verbosity;
}

void CopyrightState::addMatcher(RegexMatcher regexMatcher)
{
  regexMatchers.push_back(regexMatcher);
}

const std::vector<RegexMatcher>& CopyrightState::getRegexMatchers() const
{
  return regexMatchers;
}